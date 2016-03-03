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

    public function testSortList()
    {
        $result = $this->executeCommand(['--sort' => 'asd', '--name' => 'asd']);
        $this->assertEquals(0, $result);
        $this->assertRegExp('/Total.*?0.*?0/', $this->getDisplay());
    }
}
