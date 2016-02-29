<?php

namespace Popstas\Transmission\Console\Command;

use Popstas\Transmission\Console\WeburgClient;
use Symfony\Component\Console\Helper\ProgressBar;
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
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Max age of series torrent', 3)
            ->addArgument('movie-id', null, 'Movie ID or URL')
            ->setHelp(<<<EOT
The <info>weburg-download</info> scans weburg.net top page and downloads popular torrents.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $this->getApplication()->getConfig();
        $weburgClient = $this->getApplication()->getWeburgClient();

        try {
            list($torrentsDir, $downloadDir) = $this->getTorrentsDirectory($input);

            $movieArgument = $input->getArgument('movie-id');
            if (isset($movieArgument)) {
                $daysMax = $config->overrideConfig($input, 'days', 'weburg-series-max-age');
                $torrentsUrls = $this->getMovieTorrentsUrls($weburgClient, $movieArgument, $daysMax);
            } else {
                $torrentsUrls = $this->getPopularTorrentsUrls($output, $weburgClient, $downloadDir);
            }

            $this->dryRun($input, $output, function () use ($weburgClient, $torrentsDir, $torrentsUrls) {
                foreach ($torrentsUrls as $torrentUrl) {
                    $weburgClient->downloadTorrent($torrentUrl, $torrentsDir);
                }
            }, 'dry-run, don\'t really download');
        } catch (\RuntimeException $e) {
            $output->writeln($e->getMessage());
            return 1;
        }

        return 0;
    }

    public function getPopularTorrentsUrls(OutputInterface $output, WeburgClient $weburgClient, $downloadDir)
    {
        $torrentsUrls = [];

        $config = $this->getApplication()->getConfig();
        $logger = $this->getApplication()->getLogger();

        $moviesIds = $weburgClient->getMoviesIds();

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
     * @param WeburgClient $weburgClient
     * @param $movieId
     * @param $daysMax
     * @return array
     * @throws \RuntimeException
     */
    public function getMovieTorrentsUrls(WeburgClient $weburgClient, $movieId, $daysMax)
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
            $seriesUrls = $weburgClient->getSeriesTorrents($movieId, $movieInfo['hashes'], $daysMax);
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
                .'Use command with --download-torrents-dir=/path/to/dir parameter '
                .'or define destination directory \'download-torrents-dir\' in config file.');
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
}
