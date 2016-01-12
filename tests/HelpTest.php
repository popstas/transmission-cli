<?php

namespace Popstas\Transmission\Console\Tests;

use Symfony\Component\Console\Tester\CommandTester;
use Popstas\Transmission\Console\Application;

class ListCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testHelpCompletion()
    {
        $application = new Application();

        $command = $application->find('list');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '_completion'
        ));

        $this->assertRegExp('/Transmission CLI/', $commandTester->getDisplay());
    }
}