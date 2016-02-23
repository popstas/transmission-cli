<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class DownloadWeburgTest extends CommandTestCase
{
    public function setUp()
    {
        $this->setCommandName('download-weburg');
        parent::setUp();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testNotExistDest()
    {
        $this->executeCommand(['--dest' => '/not/exists/directory']);
    }
}
