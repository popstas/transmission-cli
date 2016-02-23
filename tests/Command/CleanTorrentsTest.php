<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class CleanTorrentsTest extends CommandTestCase
{
    public function setUp()
    {
        $this->setCommandName('clean-torrents');
        parent::setUp();
    }

    /*public function testWithoutOptions()
    {
        $this->executeCommand();
    }*/

    public function testDryRun()
    {
        $this->executeCommand(['--dry-run' => true]);
    }
}
