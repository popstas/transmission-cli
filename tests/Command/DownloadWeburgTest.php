<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Config;
use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class DownloadWeburgTest extends CommandTestCase
{
    public function setUp()
    {
        $this->setCommandName('download-weburg');
        parent::setUp();

        $this->app->setConfig(new Config());

        $httpClient = $this->getMock('GuzzleHttp\ClientInterface');
        $client = $this->getMock('Popstas\Transmission\Console\WeburgClient', [], [$httpClient]);
        $client->method('getMoviesIds')->will($this->returnValue([1, 2, 3]));

        $this->getCommand()->setWeburgClient($client);
    }

    // TODO: check that all skipped
    public function testDownloadPopular()
    {
        $client = $this->getCommand()->getWeburgClient();
        $client->method('isTorrentPopular')->will($this->onConsecutiveCalls(true, false, true));
        $client->method('getMovieTorrentUrlsById')->will($this->returnValue(['http://torrent-url']));
        $client->expects($this->exactly(2))->method('downloadTorrent');

        $dest = sys_get_temp_dir();
        $this->executeCommand(['--dest' => $dest]);

        // TODO: dirty downloaded variable define
        $files = glob($dest . '/downloaded/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($dest . '/downloaded');
    }

    // TODO: check that all skipped
    public function testDownloadDownloaded()
    {
        $client = $this->getCommand()->getWeburgClient();
        $client->method('isTorrentPopular')->will($this->onConsecutiveCalls(true, false, true));
        $client->method('getMovieTorrentUrlsById')->will($this->returnValue(['http://torrent-url']));
        $client->expects($this->exactly(1))->method('downloadTorrent');

        $dest = sys_get_temp_dir();
        mkdir($dest . '/downloaded');
        file_put_contents($dest . '/downloaded/1', '');

        $this->executeCommand(['--dest' => $dest]);

        // TODO: dirty downloaded variable define
        $files = glob($dest . '/downloaded/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($dest . '/downloaded');
    }

    public function testAllPopularDryRun()
    {
        $dest = sys_get_temp_dir();
        $client = $this->getCommand()->getWeburgClient();
        $client->method('isTorrentPopular')->will($this->returnValue(true));
        $this->executeCommand(['--dry-run' => true, '--dest' => $dest]);
    }

    // TODO: check that all skipped
    public function testAllNotPopular()
    {
        $dest = sys_get_temp_dir();
        $this->executeCommand(['--dry-run' => true, '--dest' => $dest]);
    }

    public function testNotExistDest()
    {
        $result = $this->executeCommand(['--dest' => '/not/exists/directory']);
        $this->assertEquals(1, $result);
    }

    // TODO: check output
    public function testDownloadDirNotDefined()
    {
        $this->app->getConfig()->set('download-torrents-dir', '');
        $result = $this->executeCommand();
        $this->assertEquals(1, $result);
    }
}
