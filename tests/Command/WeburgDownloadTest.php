<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Config;
use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class WeburgDownloadTest extends CommandTestCase
{
    private $dest;

    private function rmdir($path)
    {
        $files = glob($path . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($path)) {
            rmdir($path);
        }
    }

    public function setUp()
    {
        $this->setCommandName('weburg-download');
        parent::setUp();

        $this->app->setConfig(new Config());
        $this->dest = sys_get_temp_dir();

        $httpClient = $this->getMock('GuzzleHttp\ClientInterface');
        $client = $this->getMock('Popstas\Transmission\Console\WeburgClient', [], [$httpClient]);
        $client->method('getMoviesIds')->will($this->returnValue([1, 2, 3]));
        $client->method('getMovieTorrentUrlsById')->will($this->returnValue(['http://torrent-url']));

        $this->app->setWeburgClient($client);
    }

    public function tearDown()
    {
        // TODO: dirty downloaded variable define
        $this->rmdir($this->dest . '/downloaded');

        parent::tearDown();
    }

    public function testDownloadPopular()
    {
        $client = $this->app->getWeburgClient();
        $client->method('isTorrentPopular')->will($this->onConsecutiveCalls(true, false, true));
        $client->expects($this->exactly(2))->method('downloadTorrent');

        $this->executeCommand(['--download-torrents-dir' => $this->dest]);
    }

    public function testDownloadDownloaded()
    {
        $client = $this->app->getWeburgClient();
        $client->method('isTorrentPopular')->will($this->onConsecutiveCalls(true, false, true));
        $client->expects($this->exactly(1))->method('downloadTorrent');

        mkdir($this->dest . '/downloaded');
        file_put_contents($this->dest . '/downloaded/1', '');

        $this->executeCommand(['--download-torrents-dir' => $this->dest]);
    }

    public function testAllPopularDryRun()
    {
        $client = $this->app->getWeburgClient();
        $client->method('isTorrentPopular')->will($this->returnValue(true));
        $client->expects($this->never())->method('downloadTorrent');

        $this->executeCommand(['--dry-run' => true, '--download-torrents-dir' => $this->dest]);
        $this->assertRegExp('/dry-run/', $this->getDisplay());
    }

    public function testAllNotPopular()
    {
        $client = $this->app->getWeburgClient();
        $client->expects($this->never())->method('getMovieTorrentUrlsById');

        $this->executeCommand(['--dry-run' => true, '--download-torrents-dir' => $this->dest]);
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

    public function testDownloadOneMovie()
    {
        $client = $this->app->getWeburgClient();
        $client->method('cleanMovieId')->willReturn(12345);
        $client->expects($this->once())->method('downloadTorrent');
        $this->executeCommand(['movie-id' => 12345]);
    }

    public function testDownloadOneSeries()
    {
        $client = $this->app->getWeburgClient();
        $client->method('getSeriesTorrents')->willReturn(['url-1', 'url-2']);
        $client->method('cleanMovieId')->willReturn(12345);
        $client->method('getMovieInfoById')->willReturn(['title' => 'series', 'hashes' => [1, 2]]);
        $client->expects($this->once())->method('getSeriesTorrents');
        $client->expects($this->exactly(2))->method('downloadTorrent');
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
