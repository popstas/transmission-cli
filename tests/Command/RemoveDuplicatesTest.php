<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class RemoveDuplicatesTest extends CommandTestCase
{
    // TODO: it checks nothing, only coverage
    public function testRemoveDuplicates()
    {
        $client = $this->app->getClient();
        $client->method('getObsoleteTorrents')->will($this->returnValue($this->expectedTorrentList));

        $command = $this->app->find('remove-duplicates');
        $commandTester = new CommandTester($command);

        $commandTester->execute(array(
            'command' => $command->getName(),
        ));
    }

    public function testNoObsolete()
    {
        $command = $this->app->find('remove-duplicates');
        $commandTester = new CommandTester($command);

        $commandTester->execute(array(
            'command' => $command->getName(),
        ));
    }

    public function testDryRun()
    {
        $command = $this->app->find('remove-duplicates');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'command' => $command->getName(),
            '--dry-run' => true,
        ]);
    }
}
