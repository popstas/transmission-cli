<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Config;
use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class WeburgDownloadTest extends CommandTestCase
{
    private $dest;

    public function setUp()
    {
        $this->setCommandName('weburg-download');
        parent::setUp();

        $this->dest = sys_get_temp_dir() . '/transmission-cli-torrents';
        if (!file_exists($this->dest)) {
            mkdir($this->dest);
        }
        $config = new Config();
        $config->set('download-torrents-dir', $this->dest);
        $config->set('weburg-series-list', [1, 2, 3]);
        $this->app->setConfig($config);

        $httpClient = $this->getMock('GuzzleHttp\ClientInterface');
        $client = $this->getMock('Popstas\Transmission\Console\WeburgClient', [], [$httpClient]);
        $client->method('getMoviesIds')->will($this->returnValue([1, 2, 3]));
        $client->method('getMovieTorrentUrlsById')->will($this->returnValue(['http://torrent-url']));
        $client->method('getSeriesTorrents')->willReturn(['torrent-url']);
        $client->method('getMovieInfoById')->willReturn(['title' => 'movie', 'comments' => 123, 'rating_imdb' => null]);

        $this->app->setWeburgClient($client);
    }

    public function tearDown()
    {
        // TODO: dirty downloaded variable define
        $this->rmdir($this->dest . '/downloaded');

        parent::tearDown();
    }

    public function testNotExistDest()
    {
        $result = $this->executeCommand(['--download-torrents-dir' => '/not/exists/directory']);
        $this->assertEquals(1, $result);
    }

    public function testDownloadDirNotDefined()
    {
        $this->app->getConfig()->set('download-torrents-dir', '');
        $result = $this->executeCommand();
        $this->assertEquals(1, $result);
        $this->assertRegExp('/Destination directory not defined/', $this->getDisplay());
    }



    public function testDownloadPopular()
    {
        $this->app->getConfig()->set('transmission', [
            ['host' => 'devnull1', 'port' => 9091, 'user' => '', 'password' => ''],
            ['host' => 'devnull2', 'port' => 9091, 'user' => '', 'password' => '']
        ]);

        $client = $this->app->getWeburgClient();
        $client->method('isTorrentPopular')->will($this->onConsecutiveCalls(true, false, true));
        $client->expects($this->exactly(2))->method('downloadTorrent');

        $this->executeCommand(['--popular' => true, '--download-torrents-dir' => $this->dest]);
        $display = $this->getDisplay();
        preg_match_all('/All torrents added/', $display, $addedCount);
        $this->assertCount(2, $addedCount[0]);
    }

    public function testDownloadPopularToOneHost()
    {
        $this->app->getConfig()->set('transmission', [
            ['host' => 'devnull1', 'port' => 9091, 'user' => '', 'password' => ''],
            ['host' => 'devnull2', 'port' => 9091, 'user' => '', 'password' => '']
        ]);

        $client = $this->app->getWeburgClient();
        $client->method('isTorrentPopular')->will($this->onConsecutiveCalls(true, false, true));
        $client->expects($this->exactly(2))->method('downloadTorrent');

        $this->executeCommand([
            '--popular' => true,
            '--download-torrents-dir' => $this->dest,
            '--transmission-host'     => 'devnull'
        ]);
        $display = $this->getDisplay();
        preg_match_all('/All torrents added/', $display, $addedCount);
        $this->assertCount(1, $addedCount[0]);
    }

    public function testDownloadDownloaded()
    {
        $client = $this->app->getWeburgClient();
        $client->method('isTorrentPopular')->will($this->onConsecutiveCalls(true, false, true));
        $client->expects($this->exactly(1))->method('downloadTorrent');

        mkdir($this->dest . '/downloaded');
        file_put_contents($this->dest . '/downloaded/1', '');

        $this->executeCommand(['--popular' => true, '--download-torrents-dir' => $this->dest]);
    }

    public function testAllPopularDryRun()
    {
        $client = $this->app->getWeburgClient();
        $client->method('isTorrentPopular')->will($this->returnValue(true));
        $client->expects($this->never())->method('downloadTorrent');

        $this->executeCommand(['--popular' => true, '--dry-run' => true, '--download-torrents-dir' => $this->dest]);
        $this->assertRegExp('/dry-run/', $this->getDisplay());
    }

    public function testAllNotPopular()
    {
        $client = $this->app->getWeburgClient();
        $client->expects($this->never())->method('getSeriesTorrents');

        $this->executeCommand(['--popular' => true, '--dry-run' => true, '--download-torrents-dir' => $this->dest]);
    }



    public function testDownloadSeries()
    {
        $httpClient = $this->getMock('GuzzleHttp\ClientInterface');
        $client = $this->getMock('Popstas\Transmission\Console\WeburgClient', [], [$httpClient]);
        $client->method('getSeriesTorrents')->willReturn(['url-1', 'url-2']);
        $client->method('getMovieInfoById')->willReturn(['title' => 'series', 'hashes' => [1, 2]]);
        $client->expects($this->exactly(3))->method('getSeriesTorrents');
        $client->expects($this->exactly(6))->method('downloadTorrent');
        $this->app->setWeburgClient($client);

        $this->executeCommand(['--series' => true, '--download-torrents-dir' => $this->dest]);
    }

    public function testDownloadEmptySeriesList()
    {
        $this->app->getConfig()->set('weburg-series-list', null);
        $client = $this->app->getClient();
        $client->expects($this->never())->method('getSeriesTorrents');

        $this->executeCommand(['--series' => true, '--download-torrents-dir' => $this->dest]);
    }



    public function testBothPopularAndSeries()
    {
        $this->executeCommand(['--download-torrents-dir' => $this->dest]);
        $display = $this->getDisplay();
        $this->assertRegExp('/series/', $display);
        $this->assertRegExp('/popular/', $display);
    }



    public function testDownloadOneMovie()
    {
        $client = $this->app->getWeburgClient();
        $client->method('cleanMovieId')->willReturn(12345);
        $client->expects($this->once())->method('downloadTorrent');
        $return = $this->executeCommand(['movie-id' => 12345]);
        $this->assertEquals(0, $return);
    }

    public function testDownloadOneSeries()
    {
        $httpClient = $this->getMock('GuzzleHttp\ClientInterface');
        $client = $this->getMock('Popstas\Transmission\Console\WeburgClient', [], [$httpClient]);
        $client->method('getSeriesTorrents')->willReturn(['url-1', 'url-2']);
        $client->method('cleanMovieId')->willReturn(12345);
        $client->method('getMovieInfoById')->willReturn(['title' => 'series', 'hashes' => [1, 2]]);
        $client->expects($this->once())->method('getSeriesTorrents');
        $client->expects($this->exactly(2))->method('downloadTorrent');
        $this->app->setWeburgClient($client);

        $this->executeCommand(['movie-id' => 12345]);
    }

    public function testDownloadOneMovieInvalid()
    {
        $client = $this->app->getWeburgClient();
        $client->method('cleanMovieId')->willReturn(false);

        $result = $this->executeCommand(['movie-id' => 12345]);
        $this->assertEquals(1, $result);
        $this->assertRegExp('/seems not weburg movie/', $this->getDisplay());
    }
}
