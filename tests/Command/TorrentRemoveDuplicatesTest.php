<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class TorrentRemoveDuplicatesTest extends CommandTestCase
{
    public function setUp()
    {
        $this->setCommandName('torrent-remove-duplicates');
        parent::setUp();
    }

    public function testRemoveDuplicates()
    {
        $this->app->getClient()->expects($this->once())->method('removeTorrents');
        $this->executeCommand();
    }

    public function testNoObsolete()
    {
        $httpClient = $this->getMock('GuzzleHttp\ClientInterface');
        $api = $this->getMock('Martial\Transmission\API\RpcClient', [], [$httpClient, '', '']);
        $client = $this->getMock('Popstas\Transmission\Console\TransmissionClient', [], [$api]);
        $client->method('getTorrentData')->will($this->returnValue([]));
        $this->app->setClient($client);

        $this->executeCommand();
        $this->assertRegExp('/There are no obsolete/', $this->getDisplay());
    }

    public function testDryRun()
    {
        $this->app->getClient()->expects($this->never())->method('removeTorrents');
        $this->executeCommand(['--dry-run' => true]);
        $this->assertRegExp('/dry-run/', $this->getDisplay());
    }
}
