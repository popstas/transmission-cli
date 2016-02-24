<?php

namespace Popstas\Transmission\Console\Command;

use GuzzleHttp;
use Popstas\Transmission\Console\WeburgClient;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DownloadWeburgCommand extends Command
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('download-weburg')
            ->setDescription('Download torrents from weburg.net')
            ->addOption('dest', null, InputOption::VALUE_OPTIONAL, 'Torrents destination directory')
            ->setHelp(<<<EOT
The <info>download-weburg</info> scans weburg.net top page and downloads popular torrents.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $this->getApplication()->getConfig();
        $logger = $this->getApplication()->getLogger();
        
        $httpClient = new GuzzleHttp\Client();
        $weburgClient = new WeburgClient($httpClient);

        $torrents_dir = $input->getOption('dest');
        if (!$torrents_dir) {
            $torrents_dir = $config->get('download-torrents-dir');
        }
        if (!$torrents_dir) {
            $output->writeln('<error>Download destination directory not set.</error>');
            $output->writeln('Use command with --dest=/path/to/dir parameter '
                .'or define destination directory in config file.');
            return 1;
        }

        $download_dir = $torrents_dir . '/downloaded';
        if (!file_exists($download_dir)) {
            throw new \RuntimeException('Destination directory not exists: ' . $torrents_dir);
            //mkdir($download_dir, 0777, true);
        }

        $movies_ids = $weburgClient->getMoviesIds();

        $progress = new ProgressBar($output, count($movies_ids));
        $progress->start();

        foreach ($movies_ids as $movie_id) {
            $progress->setMessage('Check movie ' . $movie_id . '...');
            $progress->advance();

            $downloaded_path = $download_dir . '/' . $movie_id;

            $isDownloaded = file_exists($downloaded_path);
            if ($isDownloaded) {
                continue;
            }

            $movie_info = $weburgClient->getMovieInfoById($movie_id);

            $isTorrentPopular = $this->isTorrentPopular(
                $movie_info,
                $config->get('download-comments-min'),
                $config->get('download-imdb-min'),
                $config->get('download-kinopoisk-min'),
                $config->get('download-votes-min')
            );

            if ($isTorrentPopular) {
                $progress->setMessage('Download movie ' . $movie_id . '...');

                $torrents_urls = $weburgClient->getMovieTorrentUrlsById($movie_id);
                if (!$input->getOption('dry-run')) {
                    $this->downloadTorrents($torrents_urls, $torrents_dir);

                    file_put_contents(
                        $downloaded_path,
                        date('Y-m-d H:i:s') . "\n" . implode("\n", $torrents_urls)
                    );
                } else {
                    $logger->info('dry-run, don\'t really download');
                }
            }
        }

        $progress->finish();
    }

    private function isTorrentPopular($movie_info, $comments_min, $imdb_min, $kinopoisk_min, $votes_min)
    {
        return $movie_info['comments'] >= $comments_min
        || $movie_info['rating_imdb'] >= $imdb_min
        || $movie_info['rating_kinopoisk'] >= $kinopoisk_min
        || $movie_info['rating_votes'] >= $votes_min;
    }

    private function downloadTorrents($torrents_urls, $torrents_dir)
    {
        if (!file_exists($torrents_dir)) {
            echo $torrents_dir . ' not found';
            return false; // TODO: replace with logger and exception
        }

        foreach ($torrents_urls as $torrent_url) {
            $client = new GuzzleHttp\Client();
            $jar = new GuzzleHttp\Cookie\CookieJar();

            $res = $client->request('GET', $torrent_url, [
                'cookies' => $jar,
            ]);

            if (!$res->getStatusCode()) {
                echo 'error ' . $res->getStatusCode();
                return false; // TODO: replace with logger and exception
            }

            $torrent_body = $res->getBody();

            $disposition = $res->getHeader('content-disposition');
            preg_match('/filename="(.*?)"/', $disposition[0], $res);
            $filename = $res[1];

            $file_path = $torrents_dir . '/' . $filename;
            file_put_contents($file_path, $torrent_body);
        }
    }
}
