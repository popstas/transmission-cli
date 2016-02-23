<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class HelpCommandTest extends CommandTestCase
{
    public function testHelpCompletion()
    {
        $command = $this->app->find('list');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
        ));

        $this->assertRegExp('/Transmission CLI/', $commandTester->getDisplay());
    }
}
