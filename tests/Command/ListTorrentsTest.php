<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class ListTorrentsTest extends CommandTestCase
{
    public function setUp()
    {
        $this->setCommandName('list-torrents');
        parent::setUp();
    }

    public function testListTorrents()
    {
        $this->executeCommand();
    }
}