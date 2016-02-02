<?php

namespace Popstas\Transmission\Console\Command;

use GuzzleHttp;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;

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
        $logger = $this->getLogger($output);
        $torrents_dir = $input->getOption('dest');
        if (!$torrents_dir) {
            $torrents_dir = $this->config->get('download-torrents-dir');
        }
        if (!$torrents_dir) {
            $output->writeln('<error>Download destination direcrory not set.</error>');
            $output->writeln('Use command with --dest=/path/to/dir parameter '
                .'or define destination directory in config file.');
            exit(1);
        }

        $download_dir = $torrents_dir . '/downloaded';
        if (!file_exists($download_dir)) {
            mkdir($download_dir, 0777, true);
        }

        $movies_ids = $this->getMoviesIds();

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

            $movie_info = $this->getTorrentInfo($movie_id);

            if ($this->isTorrentPopular($movie_info)) {
                $progress->setMessage('Download movie ' . $movie_id . '...');

                $torrents_urls = $this->getTorrentUrls($movie_id, $logger);
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

    private function getUrlBody($url, $headers = [])
    {
        $client = new GuzzleHttp\Client();
        $jar = new GuzzleHttp\Cookie\CookieJar();

        $res = $client->request('GET', $url, [
            'headers' => $headers,
            'cookies' => $jar,
        ]);

        if (!$res->getStatusCode()) {
            echo 'error ' . $res->getStatusCode();
            exit(1);
        }

        return $res->getBody();
    }

    private function getMoviesIds()
    {
        $movie_id_regex = '\/movies\/info\/([0-9]+)';
        $movies_url = 'http://weburg.net/movies/new/?clever_title=1&template=0&last=0';

        $json_raw = $this->getUrlBody($movies_url, [
            'Content-Type'     => 'text/html; charset=utf-8',
            'User-Agent'       => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:27.0) Gecko/20100101 Firefox/27.0',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $movies_json = json_decode($json_raw);
        preg_match_all('/' . $movie_id_regex . '/', $movies_json->items, $res);
        $movies_ids = array_unique($res[1]);

        return $movies_ids;
    }

    private function getTorrentInfo($movie_id)
    {
        $movie_url = 'http://weburg.net/movies/info/' . $movie_id;

        $info = array();

        $body = $this->getUrlBody($movie_url);
        $body = iconv('WINDOWS-1251', 'UTF-8', $body);

        preg_match('/Комментариев:&nbsp;(\d+)/', $body, $res);
        $info['comments'] = count($res) ? $res[1] : 0;

        preg_match('/external-ratings-link_type_kinopoisk.*?>(\d+\.\d+)/mis', $body, $res);
        $info['rating_imdb'] = count($res) ? $res[1] : 0;

        preg_match('/external-ratings-link_type_imdb.*?>(\d+\.\d+)/mis', $body, $res);
        $info['rating_kinopoisk'] = count($res) ? $res[1] : 0;

        preg_match('/count-votes" value="([0-9]+)"/mis', $body, $res);
        $info['rating_votes'] = count($res) ? $res[1] : 0;

        if (!$info['comments'] || !$info['comments'] || !$info['comments'] || !$info['comments']) {
            printf("Cannot find all information about movie %s\n%s", $movie_url);
            print_r($info);
        }

        return $info;
    }

    private function getTorrentUrls($movie_id, ConsoleLogger $logger)
    {
        $torrent_url = sprintf('http://weburg.net/ajax/download/movie?obj_id=%d', $movie_id);

        $body = $this->getUrlBody($torrent_url);

        preg_match_all('/(http:\/\/.*?)"/', $body, $res);
        $torrents_urls = $res[1];

        foreach ($torrents_urls as $torrents_url) {
            $logger->debug('found torrent url: ' . $torrents_url);
        }

        return $torrents_urls;
    }

    private function isTorrentPopular($movie_info)
    {
        return $movie_info['comments'] >= $this->config->get('download-comments-min')
        || $movie_info['rating_imdb'] >= $this->config->get('download-imdb-min')
        || $movie_info['rating_kinopoisk'] >= $this->config->get('download-kinopoisk-min')
        || $movie_info['rating_votes'] >= $this->config->get('download-votes-min');
    }

    private function downloadTorrents($torrents_urls, $torrents_dir)
    {
        if (!file_exists($torrents_dir)) {
            echo $torrents_dir . ' not found';
            exit(1);
        }

        foreach ($torrents_urls as $torrent_url) {
            $client = new GuzzleHttp\Client();
            $jar = new GuzzleHttp\Cookie\CookieJar();

            $res = $client->request('GET', $torrent_url, [
                'cookies' => $jar,
            ]);

            if (!$res->getStatusCode()) {
                echo 'error ' . $res->getStatusCode();
                exit(1);
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
