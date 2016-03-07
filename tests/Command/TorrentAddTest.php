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
        $torrentFile = tempnam(sys_get_temp_dir(), 'torrent');
        $this->app->getClient()->expects($this->once())->method('addTorrent');
        $this->executeCommand(['torrent-files' => [$torrentFile]]);
        $this->assertRegExp('/' . basename($torrentFile) . ' added/', $this->getDisplay());
        unlink($torrentFile);
    }

    public function testAddSeveral()
    {
        $this->app->getClient()->expects($this->exactly(2))->method('addTorrent');
        $this->executeCommand(['torrent-files' => ['url-1', 'url-2']]);
        $this->assertRegExp('/added/', $this->getDisplay());
    }

    public function testDryRun()
    {
        $this->app->getClient()->expects($this->never())->method('addTorrent');
        $this->executeCommand(['torrent-files' => ['/non/exists/file/'], '--dry-run' => true]);
    }
}
