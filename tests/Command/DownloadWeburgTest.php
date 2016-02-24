<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class DownloadWeburgTest extends CommandTestCase
{
    public function setUp()
    {
        $this->setCommandName('download-weburg');
        parent::setUp();

        $httpClient = $this->getMock('GuzzleHttp\ClientInterface');
        $client = $this->getMock('Popstas\Transmission\Console\WeburgClient', [], [$httpClient]);
        $client->method('getMoviesIds')->will($this->returnValue([1, 2, 3]));

        $command = $this->getCommand();
        $command->setWeburgClient($client);
    }

    public function testDryRun()
    {
        $this->executeCommand();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testNotExistDest()
    {
        $this->executeCommand(['--dest' => '/not/exists/directory']);
    }
}
