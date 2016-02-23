<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class RemoveDuplicatesTest extends CommandTestCase
{
    public function setUp()
    {
        $this->setCommandName('remove-duplicates');
        parent::setUp();
    }

    // TODO: it checks nothing, only coverage
    public function testRemoveDuplicates()
    {
        $client = $this->app->getClient();
        $client->method('getObsoleteTorrents')->will($this->returnValue($this->expectedTorrentList));

        $this->executeCommand();
    }

    public function testNoObsolete()
    {
        $this->executeCommand();
    }

    public function testDryRun()
    {
        $this->executeCommand(['--dry-run' => true]);
    }
}
