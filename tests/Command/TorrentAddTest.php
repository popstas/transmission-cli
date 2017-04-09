<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class TorrentAddTest extends CommandTestCase
{
    public function setUp()
    {
        $this->setCommandName('torrent-add');
        parent::setUp();
    }

    public function testAddOne()
    {
        $this->app->getClient()->method('addTorrent')->willReturn([
            'hashString' => '29cf5b5a005af16250801fd1586efae39604c0f5',
            'id' => 101,
            'name' => 'movie.mkv',
        ]);

        $torrentFile = tempnam(sys_get_temp_dir(), 'torrent');
        $this->app->getClient()->expects($this->once())->method('addTorrent');
        $this->executeCommand(['torrent-files' => [$torrentFile], '-y' => true]);
        $this->assertRegExp('/' . basename($torrentFile) . ' added/', $this->getDisplay());
        unlink($torrentFile);
    }

    public function testAddSeveral()
    {
        $this->app->getClient()->method('addTorrent')->willReturn([
            'hashString' => '29cf5b5a005af16250801fd1586efae39604c0f5',
            'id' => 101,
            'name' => 'movie.mkv',
        ]);

        $this->app->getClient()->expects($this->exactly(2))->method('addTorrent');
        $this->executeCommand(['torrent-files' => ['url-1', 'url-2'], '-y' => true]);
        $this->assertRegExp('/added/', $this->getDisplay());
    }

    public function testAddDuplicate()
    {
        $this->app->getClient()->method('addTorrent')->willReturn(['duplicate' => true]);

        $this->app->getClient()->expects($this->once())->method('addTorrent');
        $this->executeCommand(['torrent-files' => ['url-1'], '-y' => true]);
        $this->assertRegExp('/added before/', $this->getDisplay());
    }

    public function testDryRun()
    {
        $this->app->getClient()->expects($this->never())->method('addTorrent');
        $this->executeCommand(['torrent-files' => ['/non/exists/file/'], '-y' => true, '--dry-run' => true]);
    }
}
