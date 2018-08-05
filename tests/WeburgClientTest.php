<?php

namespace Popstas\Transmission\Console\Tests;

use GuzzleHttp;
use GuzzleHttp\ClientInterface;
use Popstas\Transmission\Console\Tests\Helpers\TestCase;
use Popstas\Transmission\Console\WeburgClient;

class WeburgClientTest extends TestCase
{
    /**
     * @var WeburgClient $client
     */
    private $client;

    /**
     * @var ClientInterface
     */
    private $httpClient;

    public function setUp()
    {
        $this->httpClient = $this->createMock('GuzzleHttp\ClientInterface');
        $this->client = new WeburgClient($this->httpClient);

        parent::setUp();
    }

    public function getClientWithBodyResponse($body)
    {
        $client = $this->getMockBuilder('Popstas\Transmission\Console\WeburgClient')
            ->setMethods(['getUrlBody'])
            ->setConstructorArgs([$this->httpClient])
            ->getMock();
        $client->method('getUrlBody')->will($this->returnValue($body));
        return $client;
    }

    // TODO: remove after series tests
    public function testGetSeriesInfo()
    {
        $body = $this->getTestPage('series_page');
        $body = iconv('WINDOWS-1251', 'UTF-8', $body);
        $info = $this->invokeMethod($this->client, 'getMovieInfo', [$body]);

        $this->assertEquals(
            $info['title'],
            'Теория большого взрыва (The Big Bang Theory) — Сериалы'
        );
        $this->assertEquals(416, $info['comments']);
        $this->assertEquals(8.6, $info['rating_kinopoisk']);
        $this->assertEquals(8.4, $info['rating_imdb']);
        $this->assertEquals(1296, $info['rating_votes']);
        $this->assertEquals(19, count($info['hashes']));
    }

    public function testGetMovieInfoById()
    {
        $client = $this->getClientWithBodyResponse($this->getTestPage('movie_page'));

        $info = $client->getMovieInfoById(12345);

        $this->assertEquals('Правнуки', $info['title']);
        $this->assertEquals(123, $info['comments']);
        $this->assertEquals(1.2, $info['rating_kinopoisk']);
        $this->assertEquals(3.4, $info['rating_imdb']);
        $this->assertEquals(456, $info['rating_votes']);
        $this->assertEquals(0, count($info['hashes']));
    }

    public function testGetMovieInfoByIdCorrupted()
    {
        $corrupted_body = str_replace('imdb', '', $this->getTestPage('movie_page'));
        $client = $this->getClientWithBodyResponse($corrupted_body);

        $info = $client->getMovieInfoById(12345);
        $this->assertNull($info['rating_imdb']);
    }

    public function testCleanMovieId()
    {
        $client = $this->getClientWithBodyResponse('');

        $this->assertEquals('12345', $client->cleanMovieId(12345));
        $this->assertEquals('12345', $client->cleanMovieId('http://weburg.net/movies/info/12345'));
        $this->assertEquals('12345', $client->cleanMovieId('http://weburg.net/series/info/12345'));
        $this->assertNull($client->cleanMovieId('http://other-url/12345'));
    }

    public function testGetMovieTorrentUrlsById()
    {
        $client = $this->getClientWithBodyResponse($this->getTestPage('movie_torrents'));

        $torrents_urls = $client->getMovieTorrentUrlsById(12345);
        $this->assertCount(2, $torrents_urls);
    }

    public function checkTorrentDateProvider()
    {
        return [
            'from previous day' => [strtotime('2016-02-16 00:00:00'), '17.02.2016'],
            'from same day'     => [strtotime('2016-02-17 00:00:00'), '17.02.2016'],
            'from unix epoch'   => [0, '17.02.2016'],
            'from next day'     => [strtotime('2016-02-18 00:00:00'), false],
        ];
    }

    /**
     * @dataProvider checkTorrentDateProvider
     * @param $timestamp
     * @param $expected_result
     */
    public function testCheckTorrentDate($timestamp, $expected_result)
    {
        $body = $this->getTestPage('series_hashed_torrents');

        $matched_date = $this->invokeMethod($this->client, 'checkTorrentDate', [$body, $timestamp]);
        $this->assertEquals($expected_result, $matched_date);
    }

    public function testCheckTorrentDateForMovie()
    {
        $body = $this->getTestPage('movie_torrents');

        $matched_date = $this->invokeMethod($this->client, 'checkTorrentDate', [$body, 0]);
        $this->assertEquals(false, $matched_date);
    }

    public function testGetTorrentsUrls()
    {
        $body = $this->getTestPage('movie_torrents');
        $urls = $this->invokeMethod($this->client, 'getTorrentsUrls', [$body]);
        $this->assertEquals(2, count($urls));

        $body = $this->getTestPage('series_hashed_torrents');
        $urls = $this->invokeMethod($this->client, 'getTorrentsUrls', [$body]);
        $this->assertEquals(1, count($urls));
    }

    public function testGetMoviesIds()
    {
        $client = $this->getClientWithBodyResponse($this->getTestPage('movies_new'));

        $movies_ids = $client->getMoviesIds();
        $this->assertCount(1, $movies_ids);
    }

    public function testGetMovieUrl()
    {
        $this->assertEquals(
            'https://weburg.net/movies/info/12345',
            $this->invokeMethod($this->client, 'getMovieUrl', [12345])
        );
    }

    public function testGetMovieTorrentUrl()
    {
        $this->assertEquals(
            'https://weburg.net/ajax/download/movie?obj_id=12345',
            $this->invokeMethod($this->client, 'getMovieTorrentUrl', [12345])
        );

        $this->assertEquals(
            'https://weburg.net/ajax/download/movie?hash=abcde&obj_id=12345',
            $this->invokeMethod($this->client, 'getMovieTorrentUrl', [12345, 'abcde'])
        );
    }

    public function testGetSeriesTorrents()
    {
        $client = $this->getClientWithBodyResponse($this->getTestPage('series_hashed_torrents'));

        $series_body = $this->getTestPage('series_page');
        $info = $this->invokeMethod($client, 'getMovieInfo', [$series_body]);

        $torrents_urls = $client->getSeriesTorrents(12345, $info['hashes'], 99999);
        $this->assertCount(19, $torrents_urls);

        $torrents_urls = $client->getSeriesTorrents(12345, $info['hashes'], 0);
        $this->assertCount(0, $torrents_urls);
    }

    public function testIsTorrentPopular()
    {
        $client = $this->getClientWithBodyResponse($this->getTestPage('movie_page'));
        $info = $client->getMovieInfoById(12345);

        $this->assertFalse($client->isTorrentPopular($info, 1000, 10, 10, 1000));

        $this->assertTrue($client->isTorrentPopular($info, 123, 10, 10, 1000));
        $this->assertTrue($client->isTorrentPopular($info, 1000, '3.4', 10, 1000));
        $this->assertTrue($client->isTorrentPopular($info, 1000, 10, 1.1, 1000));
        $this->assertTrue($client->isTorrentPopular($info, 1000, 10, 10, 0));
    }

    public function testDownloadTorrent()
    {
        $response = $this->createMock('\Psr\Http\Message\ResponseInterface');
        $response->method('getStatusCode')->will($this->returnValue(200));
        $response->method('getBody')->will($this->returnValue('mock'));
        $response->method('getHeader')->will($this->returnValue(['filename="movie.torrent"']));
        $this->httpClient->method('request')->will($this->returnValue($response));

        $dest = sys_get_temp_dir();

        $this->client->downloadTorrent('http://torrent-url', $dest);
        $this->assertFileExists($dest . '/movie.torrent');
        unlink($dest . '/movie.torrent');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testDownloadTorrentError()
    {
        $guzzleResponse = $this->createMock('\Psr\Http\Message\ResponseInterface');
        $guzzleResponse->method('getStatusCode')->will($this->returnValue(404));
        $this->httpClient->method('request')->will($this->returnValue($guzzleResponse));

        $this->client->downloadTorrent('http://torrent-url', '/not/matter');
    }

    public function testGetUrlBody()
    {
        $response = $this->createMock('\Psr\Http\Message\ResponseInterface');
        $response->method('getStatusCode')->will($this->returnValue(200));
        $response->method('getBody')->will($this->returnValue('mock'));
        $this->httpClient->method('request')->will($this->returnValue($response));

        $this->client->getUrlBody('http://google.com');
        $this->assertTrue(true);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGetUrlBodyError()
    {
        $guzzleResponse = $this->createMock('\Psr\Http\Message\ResponseInterface');
        $guzzleResponse->method('getStatusCode')->will($this->returnValue(404));
        $guzzleResponse->method('getBody')->will($this->returnValue('mock'));
        $this->httpClient->method('request')->will($this->returnValue($guzzleResponse));

        $this->client->getUrlBody('http://google.com/notfound');
    }
}
