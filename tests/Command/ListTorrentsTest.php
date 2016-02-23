<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ListTorrentsTest extends CommandTestCase
{
    public function testListTorrents()
    {
        $command = $this->app->find('list-torrents');
        $commandTester = new CommandTester($command);

        $commandTester->execute(array(
            'command' => $command->getName(),
        ));
    }
}
