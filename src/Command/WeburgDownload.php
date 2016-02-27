<?php

namespace Popstas\Transmission\Console\Command;

use GuzzleHttp;
use Popstas\Transmission\Console\WeburgClient;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WeburgDownload extends Command
{
    /**
     * @var WeburgClient
     */
    private $weburgClient;

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('weburg-download')
            ->setAliases(['wd'])
            ->setDescription('Download torrents from weburg.net')
            ->addOption('download-torrents-dir', null, InputOption::VALUE_OPTIONAL, 'Torrents destination directory')
            ->setHelp(<<<EOT
The <info>weburg-download</info> scans weburg.net top page and downloads popular torrents.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $this->getApplication()->getConfig();
        $logger = $this->getApplication()->getLogger();

        $weburgClient = $this->getWeburgClient();
        if (!isset($weburgClient)) {
            $this->setWeburgClient($this->createWeburgClient());
            $weburgClient = $this->getWeburgClient();
        }

        try {
            list($torrentsDir, $downloadDir) = $this->getTorrentsDirectory($input);
        } catch (\RuntimeException $e) {
            $output->writeln($e->getMessage());
            return 1;
        }

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

                $torrentsUrls = $weburgClient->getMovieTorrentUrlsById($movieId);
                if (!$input->getOption('dry-run')) {
                    $this->downloadTorrents($torrentsUrls, $torrentsDir, $downloadedLogfile);
                } else {
                    $output->writeln('dry-run, don\'t really download');
                }
            }
        }

        $progress->finish();
        return 0;
    }

    public function getWeburgClient()
    {
        return $this->weburgClient;
    }

    public function setWeburgClient($weburgClient)
    {
        $this->weburgClient = $weburgClient;
    }

    public function createWeburgClient()
    {
        $httpClient = new GuzzleHttp\Client();
        return new WeburgClient($httpClient);
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

    private function downloadTorrents($torrentsUrls, $torrentsDir, $downloadedLogfile = '')
    {
        foreach ($torrentsUrls as $torrentUrl) {
            $this->weburgClient->downloadTorrent($torrentUrl, $torrentsDir);
        }

        if ($downloadedLogfile) {
            file_put_contents(
                $downloadedLogfile,
                date('Y-m-d H:i:s') . "\n" . implode("\n", $torrentsUrls)
            );
        }
    }
}
