<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class HelpCommandTest extends CommandTestCase
{
    public function setUp()
    {
        $this->setCommandName('list');
        parent::setUp();
    }

    public function testHelpCompletion()
    {
        $this->executeCommand();

        $this->assertRegExp('/Transmission CLI/', $this->getDisplay());
    }
}
