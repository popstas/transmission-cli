<?php

namespace Popstas\Transmission\Console\Command;

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
        $logger = $this->getApplication()->getLogger();

        $weburgClient = $this->getApplication()->getWeburgClient();
        if (!isset($weburgClient)) {
            $this->getApplication()->setWeburgClient($this->createWeburgClient());
            $weburgClient = $this->getApplication()->getWeburgClient();
        }

        try {
            list($torrentsDir, $downloadDir) = $this->getTorrentsDirectory($input);
        } catch (\RuntimeException $e) {
            $output->writeln($e->getMessage());
            return 1;
        }

        $torrentsUrls = [];

        $movieArgument = $input->getArgument('movie-id');
        if (isset($movieArgument)) {
            $movieId = $weburgClient->cleanMovieId($movieArgument);
            if (!$movieId) {
                $output->writeln($movieArgument . ' seems not weburg movie ID or URL');
                return 1;
            }

            $movieInfo = $weburgClient->getMovieInfoById($movieId);
            $logger->info('Search series ' . $movieId);
            if (!empty($movieInfo['hashes'])) {
                $days = $config->overrideConfig($input, 'days', 'weburg-series-max-age');
                $seriesUrls = $weburgClient->getSeriesTorrents($movieId, $movieInfo['hashes'], $days);
                $torrentsUrls = array_merge($torrentsUrls, $seriesUrls);

                if (count($seriesUrls)) {
                    $logger->info('Download series ' . $movieId . ': '
                        . $movieInfo['title'] . ' (' . count($seriesUrls) . ')');
                }
            } else {
                $torrentsUrls = array_merge($torrentsUrls, $weburgClient->getMovieTorrentUrlsById($movieId));
            }

        } else {

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
                if (!isset($movieInfo['title'])
                    || !isset($movieInfo['comments'])
                    || !isset($movieInfo['rating_kinopoisk'])
                    || !isset($movieInfo['rating_imdb'])
                    || !isset($movieInfo['rating_votes'])
                ) {
                    $logger->warning('Cannot find all information about movie ' . $movieId);
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
        }

        if (!$input->getOption('dry-run')) {
            foreach ($torrentsUrls as $torrentUrl) {
                $weburgClient->downloadTorrent($torrentUrl, $torrentsDir);
            }
        } else {
            $output->writeln('dry-run, don\'t really download');
        }

        return 0;
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
