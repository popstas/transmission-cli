<?php

namespace Popstas\Transmission\Console\Command;

use Popstas\Transmission\Console\WeburgClient;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WeburgDownload extends Command
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('weburg-download')
            ->setAliases(['wd'])
            ->setDescription('Download torrents from weburg.net')
            ->addOption('download-torrents-dir', null, InputOption::VALUE_OPTIONAL, 'Torrents destination directory')
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Max age of series torrent')
            ->addOption('popular', null, InputOption::VALUE_NONE, 'Download only popular')
            ->addOption('series', null, InputOption::VALUE_NONE, 'Download only tracked series')
            ->addOption('query', null, InputOption::VALUE_OPTIONAL, 'Search and download movie from Weburg')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Don\'t ask confirmation')
            ->addArgument('movie-id', null, 'Movie ID or URL')
            ->setHelp(<<<EOT
## Download torrents from Weburg.net

You can automatically download popular torrents from http://weburg.net/movies/new out of the box, use command:
```
transmission-cli weburg-download --download-torrents-dir=/path/to/torrents/directory
```

or define `download-torrents-dir` in config and just:
```
transmission-cli weburg-download
```

You can automatically download new series, for add series to tracked list see `transmission-cli weburg-series-add`.
It is pretty simple:
```
transmission-cli weburg-series-add http://weburg.net/series/info/12345
```

After that command `weburg-download` also will download series from list for last day.
If you don't want to download popular torrents, but only new series, use command:
```
transmission-cli weburg-download --download-torrents-dir=/path/to/torrents/directory --series
```

## Add downloaded torrents to Transmission

After download all torrents, command call `torrent-add` command for each transmission-host from config.
If was defined `--transmission-host` option, then `torrent-add` will called only for this host.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $this->getApplication()->getConfig();
        $weburgClient = $this->getApplication()->getWeburgClient();

        try {
            list($torrentsDir, $downloadDir) = $this->getTorrentsDirectory($input);

            $daysMax = $config->overrideConfig($input, 'days', 'weburg-series-max-age');
            $allowedMisses = $config->get('weburg-series-allowed-misses');

            $movieArgument = $input->getArgument('movie-id');
            if (isset($movieArgument)) {
                $torrentsUrls = $this->getMovieTorrentsUrls(
                    $weburgClient,
                    $movieArgument,
                    $daysMax,
                    $allowedMisses
                );
            } else {
                $torrentsUrls = $this->getTorrentsUrls(
                    $input,
                    $output,
                    $weburgClient,
                    $downloadDir,
                    $daysMax,
                    $allowedMisses
                );
            }

            $this->dryRun($input, $output, function () use (
                $input,
                $output,
                $weburgClient,
                $torrentsDir,
                $torrentsUrls
            ) {
                if (empty($torrentsUrls)) {
                    $output->writeln("\nNo torrents for download");
                    return;
                }

                $downloadedFiles = [];
                foreach ($torrentsUrls as $torrentUrl) {
                    $downloadedFiles[] = $weburgClient->downloadTorrent($torrentUrl, $torrentsDir);
                }

                $downloadedFiles = $this->filterByLists($downloadedFiles);
                if (!empty($downloadedFiles)) {
                    $this->addTorrents($input, $output, $downloadedFiles);
                } else {
                    $output->writeln("\nAll torrents filtered by black/whitelists");
                }
            }, 'dry-run, don\'t really download');
        } catch (\RuntimeException $e) {
            $output->writeln($e->getMessage());
            return 1;
        }

        return 0;
    }

    private function getTorrentsUrls(
        InputInterface $input,
        OutputInterface $output,
        WeburgClient $weburgClient,
        $downloadDir,
        $daysMax,
        $allowedMisses
    ) {
        $torrentsUrls = [];

        if ($input->getOption('query')) {
            $torrentsUrls = array_merge(
                $torrentsUrls,
                $this->getTorrentsUrlByQuery($output, $weburgClient, $downloadDir, $input->getOption('query'))
            );
        }

        if (!$input->getOption('popular') && !$input->getOption('series') && !$input->getOption('query')) {
            $input->setOption('popular', true);
            $input->setOption('series', true);
        }

        if ($input->getOption('popular')) {
            $torrentsUrls = array_merge(
                $torrentsUrls,
                $this->getPopularTorrentsUrls($output, $weburgClient, $downloadDir)
            );
        }

        if ($input->getOption('series')) {
            $torrentsUrls = array_merge(
                $torrentsUrls,
                $this->getTrackedSeriesUrls($output, $weburgClient, $daysMax, $allowedMisses)
            );
        }

        return $torrentsUrls;
    }

    public function getTorrentsUrlByQuery(OutputInterface $output, WeburgClient $weburgClient, $downloadDir, $query)
    {
        $torrentsUrls = [];

        $logger = $this->getApplication()->getLogger();

        $movieId = $weburgClient->getMovieIdByQuery($query);
        if(!$movieId){
            $output->writeln("\nNot found any for query $query");
        }

        $downloadedLogfile = $downloadDir . '/' . $movieId;

        $isDownloaded = file_exists($downloadedLogfile);
        if ($isDownloaded) {
            $output->writeln("\nMovie $query was downloaded before");
        }

        $movieInfo = $weburgClient->getMovieInfoById($movieId);
        foreach (array_keys($movieInfo) as $infoField) {
            if (!isset($movieInfo[$infoField])) {
                $logger->warning('Cannot find ' . $infoField . ' in movie ' . $movieId);
            }
        }

        $movieUrls = $weburgClient->getMovieTorrentUrlsById($movieId);
        $torrentsUrls = array_merge($torrentsUrls, $movieUrls);
        $logger->info('Download movie ' . $movieId . ': ' . $movieInfo['title']);

        file_put_contents(
            $downloadedLogfile,
            date('Y-m-d H:i:s') . "\n" . implode("\n", $torrentsUrls)
        );

        return $torrentsUrls;
    }

    public function getPopularTorrentsUrls(OutputInterface $output, WeburgClient $weburgClient, $downloadDir)
    {
        $torrentsUrls = [];

        $config = $this->getApplication()->getConfig();
        $logger = $this->getApplication()->getLogger();

        $moviesIds = $weburgClient->getMoviesIds();

        $output->writeln("\nDownloading popular torrents");

        $progress = new ProgressBar($output, count($moviesIds));
        $progress->start();

        foreach ($moviesIds as $movieId) {
            $progress->setMessage('Check movie ' . $movieId . '...');
            $progress->advance();

            $downloadedLogfile = $downloadDir . '/' . $movieId;

            $isDownloaded = file_exists($downloadedLogfile);
            if ($isDownloaded) {
                continue;
            }

            $movieInfo = $weburgClient->getMovieInfoById($movieId);
            foreach (array_keys($movieInfo) as $infoField) {
                if (!isset($movieInfo[$infoField])) {
                    $logger->warning('Cannot find ' . $infoField . ' in movie ' . $movieId);
                }
            }

            $isTorrentPopular = $weburgClient->isTorrentPopular(
                $movieInfo,
                $config->get('download-comments-min'),
                $config->get('download-imdb-min'),
                $config->get('download-kinopoisk-min'),
                $config->get('download-votes-min')
            );

            if ($isTorrentPopular) {
                $progress->setMessage('Download movie ' . $movieId . '...');

                $movieUrls = $weburgClient->getMovieTorrentUrlsById($movieId);
                $torrentsUrls = array_merge($torrentsUrls, $movieUrls);
                $logger->info('Download movie ' . $movieId . ': ' . $movieInfo['title']);

                file_put_contents(
                    $downloadedLogfile,
                    date('Y-m-d H:i:s') . "\n" . implode("\n", $torrentsUrls)
                );
            }
        }

        $progress->finish();

        return $torrentsUrls;
    }

    /**
     * @param OutputInterface $output
     * @param WeburgClient $weburgClient
     * @param $daysMax
     * @param $allowedMisses
     * @return array
     */
    public function getTrackedSeriesUrls(OutputInterface $output, WeburgClient $weburgClient, $daysMax, $allowedMisses)
    {
        $torrentsUrls = [];

        $config = $this->getApplication()->getConfig();

        $seriesList = $config->get('weburg-series-list');
        if (!$seriesList) {
            return [];
        }

        $output->writeln("\nDownloading tracked series");

        $progress = new ProgressBar($output, count($seriesList));
        $progress->start();

        foreach ($seriesList as $seriesItem) {
            if (is_array($seriesItem)) {
                $seriesId = $seriesItem['id'];
                $seriesTitle = isset($seriesItem['title']) && $seriesItem['title'] ? $seriesItem['title'] : $seriesId;
            } else {
                $seriesId = $seriesTitle = $seriesItem;
            }
            $progress->setMessage('Check series ' . $seriesTitle . '...');
            $progress->advance();

            $movieInfo = $weburgClient->getMovieInfoById($seriesId);
            $seriesUrls = $weburgClient->getSeriesTorrents($seriesId, $movieInfo['hashes'], $daysMax, $allowedMisses);
            $torrentsUrls = array_merge($torrentsUrls, $seriesUrls);
        }

        $progress->finish();

        return $torrentsUrls;
    }

    /**
     * @param WeburgClient $weburgClient
     * @param $movieId
     * @param $daysMax
     * @param $allowedMisses
     * @return array
     */
    public function getMovieTorrentsUrls(WeburgClient $weburgClient, $movieId, $daysMax, $allowedMisses)
    {
        $torrentsUrls = [];
        $logger = $this->getApplication()->getLogger();

        $movieId = $weburgClient->cleanMovieId($movieId);
        if (!$movieId) {
            throw new \RuntimeException($movieId . ' seems not weburg movie ID or URL');
        }

        $movieInfo = $weburgClient->getMovieInfoById($movieId);
        $logger->info('Search series ' . $movieId);
        if (!empty($movieInfo['hashes'])) {
            $seriesUrls = $weburgClient->getSeriesTorrents($movieId, $movieInfo['hashes'], $daysMax, $allowedMisses);
            $torrentsUrls = array_merge($torrentsUrls, $seriesUrls);

            if (count($seriesUrls)) {
                $logger->info('Download series ' . $movieId . ': '
                    . $movieInfo['title'] . ' (' . count($seriesUrls) . ')');
            }
        } else {
            $torrentsUrls = array_merge($torrentsUrls, $weburgClient->getMovieTorrentUrlsById($movieId));
        }

        return $torrentsUrls;
    }

    /**
     * @param InputInterface $input
     * @return array
     * @throws \RuntimeException
     */
    private function getTorrentsDirectory(InputInterface $input)
    {
        $config = $this->getApplication()->getConfig();

        $torrentsDir = $config->overrideConfig($input, 'download-torrents-dir');
        if (!$torrentsDir) {
            throw new \RuntimeException('Destination directory not defined. '
                . 'Use command with --download-torrents-dir=/path/to/dir parameter '
                . 'or define destination directory \'download-torrents-dir\' in config file.');
        }

        if (!file_exists($torrentsDir)) {
            throw new \RuntimeException('Destination directory not exists: ' . $torrentsDir);
        }

        $downloadDir = $torrentsDir . '/downloaded';
        if (!file_exists($downloadDir)) {
            mkdir($downloadDir, 0777);
        }

        return [$torrentsDir, $downloadDir];
    }

    private function filterByLists(array $torrentFiles)
    {
        $config = $this->getApplication()->getConfig();
        $logger = $this->getApplication()->getLogger();

        $whitelist = $config->get('download-filename-whitelist');
        $blacklist = $config->get('download-filename-blacklist');

        $torrentFiles = array_filter($torrentFiles, function ($torrentFile) use ($whitelist, $blacklist, $logger) {
            if (!empty($whitelist)) {
                $matched = false;
                foreach ($whitelist as $white) {
                    if (preg_match('/' . $white . '/i', $torrentFile)) {
                        $logger->info($torrentFile . ' matched whitelist: ' . $white);
                        $matched = true;
                    }
                }
                if (!$matched) {
                    $logger->info($torrentFile . ' not matched any whitelist: ' . implode(', ', $whitelist));
                    return false;
                }
            }
            if (!empty($blacklist)) {
                foreach ($blacklist as $black) {
                    if (preg_match('/' . $black . '/i', $torrentFile)) {
                        $logger->info($torrentFile . ' matched blacklist: ' . $black);
                        return false;
                    }
                }
            }
            return true;
        });
        return $torrentFiles;
    }

    private function addTorrents(InputInterface $input, OutputInterface $output, array $torrentFiles)
    {
        $config = $this->getApplication()->getConfig();
        $hosts = [];

        if (empty($input->getOption('transmission-host'))) {
            $transmissionConnects = $config->get('transmission');
            foreach ($transmissionConnects as $transmissionConnect) {
                $hosts[] = $transmissionConnect['host'];
            }
        } else {
            $hosts[] = $config->get('transmission-host');
        }

        foreach ($hosts as $host) {
            $command = $this->getApplication()->find('torrent-add');
            $arguments = array(
                'command'             => 'torrent-add',
                'torrent-files'       => $torrentFiles,
                '--transmission-host' => $host,
                '--yes'               => $input->getOption('yes'),
                '--dry-run'           => $input->getOption('dry-run'),
            );

            $addInput = new ArrayInput($arguments);
            $output->writeln("\nAdd " . count($torrentFiles) . " torrents to " . $host);
            $command->run($addInput, $output);
        }
    }
}
