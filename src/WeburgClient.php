<?php

namespace Popstas\Transmission\Console;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;

class WeburgClient
{
    /**
     * @var ClientInterface
     */
    private $httpClient;

    public function __construct(ClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function getMovieInfoById($movie_id)
    {
        $movie_url = $this->getMovieUrl($movie_id);
        $body = $this->getUrlBody($movie_url);
        $body = iconv('WINDOWS-1251', 'UTF-8', $body);
        $info = $this->getMovieInfo($body);
        return $info;
    }

    /**
     * @param $movie_id
     * @return array urls of movie (not series)
     */
    public function getMovieTorrentUrlsById($movie_id)
    {
        $torrent_url = $this->getMovieTorrentUrl($movie_id);
        $body = $this->getUrlBody($torrent_url);
        $torrents_urls = $this->getTorrentsUrls($body);
        return $torrents_urls;
    }

    /**
     * @param $movie_id
     * @param $hashes array hashes of torrents from movieInfo
     * @param int $last_days torrents older last days will not matched
     * @return array urls of matched torrent files
     */
    public function getSeriesTorrents($movie_id, $hashes, $last_days = 1)
    {
        $torrents_urls = [];
        $timestamp_from = strtotime('-' . $last_days . 'days');

        $hashes = array_reverse($hashes);
        foreach ($hashes as $hash) {
            $torrent_url = $this->getMovieTorrentUrl($movie_id, $hash);
            $body = $this->getUrlBody($torrent_url);

            if (!$this->checkTorrentDate($body, $timestamp_from)) {
                break;
            }
            $torrents_urls = array_merge($torrents_urls, $this->getTorrentsUrls($body));
        }

        return $torrents_urls;
    }

    public function getMoviesIds()
    {
        $movies_url = 'http://weburg.net/movies/new/?clever_title=1&template=0&last=0';

        $json_raw = $this->getUrlBody($movies_url, [
            'Content-Type'     => 'text/html; charset=utf-8',
            'User-Agent'       => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:27.0) Gecko/20100101 Firefox/27.0',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $movies_json = json_decode($json_raw);

        $movies_ids = $this->getInfoUrls($movies_json->items);

        return $movies_ids;
    }

    public function getMovieUrl($movie_id)
    {
        return 'http://weburg.net/movies/info/' . $movie_id;
    }

    public function getUrlBody($url, $headers = [])
    {
        $jar = new CookieJar();

        $res = $this->httpClient->request('GET', $url, [
            'headers' => $headers,
            'cookies' => $jar,
        ]);

        // TODO: it should return 200 or not 200 code, never 0
        if ($res->getStatusCode() != 200) {
            throw new \RuntimeException('Error ' . $res->getStatusCode() . 'while get url ' . $url);
        }

        $body = $res->getBody();

        return $body;
    }

    public function isTorrentPopular($movie_info, $comments_min, $imdb_min, $kinopoisk_min, $votes_min)
    {
        return $movie_info['comments'] >= $comments_min
        || $movie_info['rating_imdb'] >= $imdb_min
        || $movie_info['rating_kinopoisk'] >= $kinopoisk_min
        || $movie_info['rating_votes'] >= $votes_min;
    }

    public function downloadTorrent($url, $torrentsDir)
    {
        $jar = new CookieJar();

        $res = $this->httpClient->request('GET', $url, ['cookies' => $jar]);

        if ($res->getStatusCode() != 200) {
            throw new \RuntimeException('Error ' . $res->getStatusCode() . 'while get url ' . $url);
        }

        $torrentBody = $res->getBody();

        $disposition = $res->getHeader('content-disposition');
        preg_match('/filename="(.*?)"/', $disposition[0], $res);
        $filename = $res[1];

        $filePath = $torrentsDir . '/' . $filename;
        file_put_contents($filePath, $torrentBody);
    }

    public function cleanMovieId($idOrUrl)
    {
        if (preg_match('/^\d+$/', $idOrUrl)) {
            return $idOrUrl;
        }
        preg_match('/^http:\/\/weburg\.net\/(series|movies)\/info\/(\d+)$/', $idOrUrl, $res);
        $movieId = count($res) ? $res[2] : null;
        return $movieId;
    }

    private function getMovieTorrentUrl($movie_id, $hash = '')
    {
        return 'http://weburg.net/ajax/download/movie?'
        . ($hash ? 'hash=' . $hash . '&' : '')
        . 'obj_id=' . $movie_id;
    }

    private function getMovieInfo($body)
    {
        $info = array();

        preg_match('/<title>(.*?) — Weburg<\/title>/mis', $body, $res);
        $info['title'] = count($res) ? $res[1] : null;

        preg_match('/Комментариев:&nbsp;(\d+)/mis', $body, $res);
        $info['comments'] = count($res) ? $res[1] : null;

        preg_match('/external-ratings-link_type_kinopoisk.*?>(\d+\.\d+)/mis', $body, $res);
        $info['rating_kinopoisk'] = count($res) ? $res[1] : null;

        preg_match('/external-ratings-link_type_imdb.*?>(\d+\.\d+)/mis', $body, $res);
        $info['rating_imdb'] = count($res) ? $res[1] : null;

        preg_match('/count-votes" value="([0-9]+)"/mis', $body, $res);
        $info['rating_votes'] = count($res) ? $res[1] : null;

        preg_match_all('/js-search-button.*?hash="(.*?)"/mis', $body, $res);
        $info['hashes'] = count($res) ? $res[1] : null;

        return $info;
    }

    /**
     * @param $body
     * @param $from_timestamp
     * @return bool|string date if matched, false if not
     */
    private function checkTorrentDate($body, $from_timestamp)
    {
        preg_match('/(\d{2})\.(\d{2})\.(\d{4})/mis', $body, $res);
        if (empty($res)) {
            return false;
        }

        $torrent_timestamp = mktime(0, 0, 0, $res[2], $res[1], $res[3]);
        return $torrent_timestamp >= $from_timestamp ? $res[0] : false;
    }

    private function getInfoUrls($body)
    {
        $movie_id_regex = '/\/movies\/info\/([0-9]+)/';
        preg_match_all($movie_id_regex, $body, $res);
        $movies_ids = array_unique($res[1]);
        return $movies_ids;
    }

    /**
     * @param $body
     * @return array
     */
    private function getTorrentsUrls($body)
    {
        preg_match_all('/(http:\/\/.*?gettorrent.*?)"/', $body, $res);
        $torrents_urls = $res[1];
        $torrents_urls = array_unique($torrents_urls);
        return $torrents_urls;
    }
}
