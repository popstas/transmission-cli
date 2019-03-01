<?php

namespace Popstas\Transmission\Console;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class WeburgClient
{
    /**
     * @var ClientInterface
     */
    private $httpClient;

    private $requestDelay;

    /**
     * WeburgClient constructor.
     * @param ClientInterface $httpClient
     * @param int $requestDelay
     */
    public function __construct(ClientInterface $httpClient, $requestDelay = 0)
    {
        $this->httpClient = $httpClient;
        $this->requestDelay = $requestDelay;
    }

    /**
     * @param $movieId
     * @return array
     * @throws GuzzleException
     */
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
     * @throws GuzzleException
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
     * @param int $allowedMisses after x misses next checks will broken
     * @return array urls of matched torrent files
     * @throws GuzzleException
     */
    public function getSeriesTorrents($movieId, $hashes, $daysMax = 1, $allowedMisses = 0)
    {
        $torrentsUrls = [];
        $timestampFrom = strtotime('-' . $daysMax . 'days');

        $hashes = array_reverse($hashes);
        foreach ($hashes as $hash) {
            if ($allowedMisses < 0) {
                break;
            }

            $torrentUrl = $this->getMovieTorrentUrl($movieId, $hash);
            $body = $this->getUrlBody($torrentUrl);

            if ($this->checkTorrentDate($body, $timestampFrom) === false) {
                $allowedMisses--;
                continue;
            }

            $torrentsUrls = array_merge($torrentsUrls, $this->getTorrentsUrls($body));
        }

        return $torrentsUrls;
    }

    /**
     * @param string $moviesUrl
     * @return array
     * @throws GuzzleException
     */
    public function getMoviesIds($moviesUrl = 'https://weburg.net/movies/new/?clever_title=1&template=0&last=0')
    {
        if (!$moviesUrl) {
            $moviesUrl = 'https://weburg.net/movies/new/?clever_title=1&template=0&last=0';
        }
        $jsonRaw = $this->getUrlBody($moviesUrl, [
            'Content-Type'     => 'text/html; charset=utf-8',
            'User-Agent'       => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:27.0) Gecko/20100101 Firefox/27.0',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $moviesJson = @json_decode($jsonRaw);
        $html = $moviesJson ? $moviesJson->items : strval($jsonRaw);

        $moviesIds = $this->getInfoUrls($html);

        return $moviesIds;
    }

    /**
     * @param $q
     * @return bool|string
     * @throws GuzzleException
     */
    public function getMovieIdByQuery($q)
    {
        $results = $this->movieQuery($q);

        if ($results && $results[0] && $results[0]->object_id) {
            return $results[0]->object_id;
        }

        return false;
    }

    /**
     * @param $q
     * @return mixed
     * @throws GuzzleException
     */
    public function movieQuery($q)
    {
        $resultUrl = 'https://weburg.net/ajax/autocomplete/search/main?' . http_build_query(['q' => $q]);

        $jsonRaw = $this->getUrlBody($resultUrl, [
            'Content-Type' => 'text/html; charset=utf-8',
            'User-Agent'   => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:27.0) Gecko/20100101 Firefox/27.0',
            // 'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $resultJson = json_decode($jsonRaw);

        return $resultJson;
    }

    public static function getMovieUrl($movieId)
    {
        return 'https://weburg.net/movies/info/' . $movieId;
    }

    /**
     * @param $url
     * @param array $headers
     * @return ResponseInterface
     * @throws RuntimeException
     * @throws GuzzleException
     */
    private function getUrl($url, $headers = [])
    {
        $jar = new CookieJar();

        $res = $this->httpClient->request('GET', $url, [
            'headers' => $headers,
            'cookies' => $jar,
        ]);

        if ($res->getStatusCode() != 200) {
            throw new RuntimeException('Error ' . $res->getStatusCode() . 'while get url ' . $url);
        }

        sleep($this->requestDelay);

        return $res;
    }

    /**
     * @param string $url
     * @param array $headers
     * @return StreamInterface
     * @throws RuntimeException
     * @throws GuzzleException
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
     * @return string path to downloaded file
     * @throws RuntimeException
     * @throws GuzzleException
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

        return $filePath;
    }

    public static function isTorrentPopular($movieInfo, $commentsMin, $imdbMin, $kinopoiskMin, $votesMin)
    {
        return $movieInfo['comments'] >= $commentsMin
            || $movieInfo['rating_imdb'] >= $imdbMin
            || $movieInfo['rating_kinopoisk'] >= $kinopoiskMin
            || $movieInfo['rating_votes'] >= $votesMin;
    }

    public static function cleanMovieId($idOrUrl)
    {
        if (preg_match('/^\d+$/', $idOrUrl)) {
            return $idOrUrl;
        }
        preg_match('/^https?:\/\/weburg\.net\/(series|movies)\/info\/(\d+)$/', $idOrUrl, $res);
        $movieId = count($res) ? $res[2] : null;
        return $movieId;
    }

    private static function getMovieTorrentUrl($movieId, $hash = '')
    {
        return 'https://weburg.net/ajax/download/movie?'
            . ($hash ? 'hash=' . $hash . '&' : '')
            . 'obj_id=' . $movieId;
    }

    private static function getMovieInfo($body)
    {
        $info = [];

        $checks = [
            'title'            => '<title>(.*?)( — Weburg)?( — Вебург)?<\/title>',
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
    private static function checkTorrentDate($body, $fromTimestamp)
    {
        preg_match('/(\d{2})\.(\d{2})\.(\d{4})/mis', $body, $res);
        if (empty($res)) {
            return false;
        }

        $torrentTimestamp = mktime(0, 0, 0, $res[2], $res[1], $res[3]);
        return $torrentTimestamp >= $fromTimestamp ? $res[0] : false;
    }

    private static function getInfoUrls($body)
    {
        preg_match_all('/\/movies\/info\/([0-9]+)/', $body, $res);
        $moviesIds = array_unique($res[1]);
        return $moviesIds;
    }

    /**
     * @param $body
     * @return array
     */
    private static function getTorrentsUrls($body)
    {
        preg_match_all('/(http:\/\/.*?gettorrent.*?)"/', $body, $res);
        $torrentsUrls = $res[1];
        $torrentsUrls = array_unique($torrentsUrls);
        return $torrentsUrls;
    }
}
