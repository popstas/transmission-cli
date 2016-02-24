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

        $this->getCommand()->setWeburgClient($client);
    }

    public function testDryRun()
    {
        $dest = sys_get_temp_dir();
        $this->executeCommand(['--dry-run' => true, '--dest' => $dest]);
    }

    public function testNotExistDest()
    {
        $result = $this->executeCommand(['--dest' => '/not/exists/directory']);
        $this->assertEquals(1, $result);
    }
}
