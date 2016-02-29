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

    private $requestDelay;

    public function __construct(ClientInterface $httpClient, $requestDelay = 0)
    {
        $this->httpClient = $httpClient;
        $this->requestDelay = $requestDelay;
    }

    public function getMovieInfoById($movieId)
    {
        $movieUrl = $this->getMovieUrl($movieId);
        $body = $this->getUrlBody($movieUrl);
        $body = iconv('WINDOWS-1251', 'UTF-8', $body);
        $info = $this->getMovieInfo($body);
        return $info;
    }

    /**
     * @param $movieId
     * @return array urls of movie (not series)
     */
    public function getMovieTorrentUrlsById($movieId)
    {
        $torrentUrl = $this->getMovieTorrentUrl($movieId);
        $body = $this->getUrlBody($torrentUrl);
        $torrentsUrls = $this->getTorrentsUrls($body);
        return $torrentsUrls;
    }

    /**
     * @param $movieId
     * @param $hashes array hashes of torrents from movieInfo
     * @param int $daysMax torrents older last days will not matched
     * @return array urls of matched torrent files
     */
    public function getSeriesTorrents($movieId, $hashes, $daysMax = 1)
    {
        $torrentsUrls = [];
        $timestampFrom = strtotime('-' . $daysMax . 'days');

        $hashes = array_reverse($hashes);
        foreach ($hashes as $hash) {
            $torrentUrl = $this->getMovieTorrentUrl($movieId, $hash);
            $body = $this->getUrlBody($torrentUrl);

            if (!$this->checkTorrentDate($body, $timestampFrom)) {
                break;
            }
            $torrentsUrls = array_merge($torrentsUrls, $this->getTorrentsUrls($body));
        }

        return $torrentsUrls;
    }

    public function getMoviesIds()
    {
        $moviesUrl = 'http://weburg.net/movies/new/?clever_title=1&template=0&last=0';

        $jsonRaw = $this->getUrlBody($moviesUrl, [
            'Content-Type'     => 'text/html; charset=utf-8',
            'User-Agent'       => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:27.0) Gecko/20100101 Firefox/27.0',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $moviesJson = json_decode($jsonRaw);

        $moviesIds = $this->getInfoUrls($moviesJson->items);

        return $moviesIds;
    }

    public function getMovieUrl($movieId)
    {
        return 'http://weburg.net/movies/info/' . $movieId;
    }

    /**
     * @param $url
     * @param array $headers
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \RuntimeException
     */
    private function getUrl($url, $headers = [])
    {
        $jar = new CookieJar();

        $res = $this->httpClient->request('GET', $url, [
            'headers' => $headers,
            'cookies' => $jar,
        ]);

        if ($res->getStatusCode() != 200) {
            throw new \RuntimeException('Error ' . $res->getStatusCode() . 'while get url ' . $url);
        }

        sleep($this->requestDelay);

        return $res;
    }

    /**
     * @param $url
     * @param array $headers
     * @return \Psr\Http\Message\StreamInterface
     * @throws \RuntimeException
     */
    public function getUrlBody($url, $headers = [])
    {
        $res = $this->getUrl($url, $headers);
        $body = $res->getBody();
        return $body;
    }

    /**
     * @param $url
     * @param $torrentsDir
     * @throws \RuntimeException
     */
    public function downloadTorrent($url, $torrentsDir)
    {
        $res = $this->getUrl($url);
        $torrentBody = $res->getBody();

        $disposition = $res->getHeader('content-disposition');
        preg_match('/filename="(.*?)"/', $disposition[0], $res);
        $filename = $res[1];

        $filePath = $torrentsDir . '/' . $filename;
        file_put_contents($filePath, $torrentBody);
    }

    public function isTorrentPopular($movieInfo, $commentsMin, $imdbMin, $kinopoiskMin, $votesMin)
    {
        return $movieInfo['comments'] >= $commentsMin
        || $movieInfo['rating_imdb'] >= $imdbMin
        || $movieInfo['rating_kinopoisk'] >= $kinopoiskMin
        || $movieInfo['rating_votes'] >= $votesMin;
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

    private function getMovieTorrentUrl($movieId, $hash = '')
    {
        return 'http://weburg.net/ajax/download/movie?'
        . ($hash ? 'hash=' . $hash . '&' : '')
        . 'obj_id=' . $movieId;
    }

    private function getMovieInfo($body)
    {
        $info = [];

        $checks = [
            'title'            => '<title>(.*?) — Weburg<\/title>',
            'comments'         => 'Комментариев:&nbsp;(\d+)',
            'rating_kinopoisk' => 'external-ratings-link_type_kinopoisk.*?>(\d+\.\d+)',
            'rating_imdb'      => 'external-ratings-link_type_imdb.*?>(\d+\.\d+)',
            'rating_votes'     => 'count-votes" value="([0-9]+)"',
        ];

        foreach ($checks as $name => $regexp) {
            preg_match('/' . $regexp . '/mis', $body, $res);
            $info[$name] = count($res) ? $res[1] : null;
        }

        preg_match_all('/js-search-button.*?hash="(.*?)"/mis', $body, $res);
        $info['hashes'] = count($res) ? $res[1] : null;

        return $info;
    }

    /**
     * @param $body
     * @param $fromTimestamp
     * @return bool|string date if matched, false if not
     */
    private function checkTorrentDate($body, $fromTimestamp)
    {
        preg_match('/(\d{2})\.(\d{2})\.(\d{4})/mis', $body, $res);
        if (empty($res)) {
            return false;
        }

        $torrentTimestamp = mktime(0, 0, 0, $res[2], $res[1], $res[3]);
        return $torrentTimestamp >= $fromTimestamp ? $res[0] : false;
    }

    private function getInfoUrls($body)
    {
        preg_match_all('/\/movies\/info\/([0-9]+)/', $body, $res);
        $moviesIds = array_unique($res[1]);
        return $moviesIds;
    }

    /**
     * @param $body
     * @return array
     */
    private function getTorrentsUrls($body)
    {
        preg_match_all('/(http:\/\/.*?gettorrent.*?)"/', $body, $res);
        $torrentsUrls = $res[1];
        $torrentsUrls = array_unique($torrentsUrls);
        return $torrentsUrls;
    }
}
