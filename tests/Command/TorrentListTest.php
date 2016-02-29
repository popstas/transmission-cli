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
        $this->executeCommand(['--sort' => 'asd']);
        $display1 = $this->getDisplay();
        $this->executeCommand(['--sort' => 0]);
        $display2 = $this->getDisplay();
        $this->assertEquals($display1, $display2);

        $this->executeCommand();
        $display1 = $this->getDisplay();
        $this->executeCommand(['--sort' => 4]);
        $display2 = $this->getDisplay();
        $this->assertEquals($display1, $display2);
    }
}
