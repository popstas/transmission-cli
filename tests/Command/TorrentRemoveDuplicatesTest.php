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

    public function testRemoveDuplicatesConfirmation()
    {
        $command = $this->getCommand();

        // confirmed
        $question = $this->getMock('Symfony\Component\Console\Helper\QuestionHelper', ['ask']);
        $question->expects($this->at(0))
            ->method('ask')
            ->will($this->returnValue(true));

        $command->getHelperSet()->set($question, 'question');

        $this->app->getClient()->expects($this->once())->method('removeTorrents');

        $result = $this->executeCommand();
        $this->assertEquals(0, $result);
        $this->assertRegExp('/Found and deleted/', $this->getDisplay());


        // not confirmed
        $question = $this->getMock('Symfony\Component\Console\Helper\QuestionHelper', ['ask']);
        $question->expects($this->at(0))
            ->method('ask')
            ->will($this->returnValue(false));

        $command->getHelperSet()->set($question, 'question');

        $this->app->getClient()->expects($this->never())->method('removeTorrents');

        $result = $this->executeCommand();
        $this->assertEquals(1, $result);
        $this->assertRegExp('/Aborting/', $this->getDisplay());
    }

    public function testRemoveDuplicates()
    {
        $this->app->getClient()->expects($this->once())->method('removeTorrents');
        $this->executeCommand(['-y' => true]);
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
        $this->executeCommand(['--dry-run' => true, '-y' => true]);
        $this->assertRegExp('/dry-run/', $this->getDisplay());
    }
}
