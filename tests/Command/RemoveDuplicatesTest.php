<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class RemoveDuplicatesTest extends CommandTestCase
{
    public function setUp()
    {
        $this->setCommandName('remove-duplicates');
        parent::setUp();

        //$this->app->getClient()->method('getObsoleteTorrents')->will($this->returnValue($this->expectedTorrentList));
    }

    public function testRemoveDuplicates()
    {
        $this->app->getClient()->method('getObsoleteTorrents')->will($this->returnValue($this->expectedTorrentList));
        $this->app->getClient()->expects($this->once())->method('removeTorrents');
        $this->executeCommand();
    }

    public function testNoObsolete()
    {
        $this->app->getClient()->method('getObsoleteTorrents')->will($this->returnValue([]));
        $this->executeCommand();
        $this->assertRegExp('/There are no obsolete/', $this->getDisplay());
    }

    public function testDryRun()
    {
        $this->app->getClient()->method('getObsoleteTorrents')->will($this->returnValue($this->expectedTorrentList));
        $this->app->getClient()->expects($this->never())->method('removeTorrents');
        $this->executeCommand(['--dry-run' => true]);
        $this->assertRegExp('/dry-run/', $this->getDisplay());
    }
}
