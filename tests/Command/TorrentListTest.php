<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class TorrentListTest extends CommandTestCase
{
    public function setUp()
    {
        $this->setCommandName('torrent-list');
        parent::setUp();
    }

    public function testListTorrents()
    {
        $this->executeCommand();
    }
}
