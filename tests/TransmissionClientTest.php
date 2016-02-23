<?php

namespace Popstas\Transmission\Console\Tests;

use Martial\Transmission\API;
use Popstas\Transmission\Console\Tests\Helpers\TestCase;
use Popstas\Transmission\Console\TransmissionClient;
use Symfony\Component\Console\Output\NullOutput;

class TransmissionClientTest extends TestCase
{
    /**
     * @var API\RpcClient $api
     */
    private $api;

    /**
     * @var TransmissionClient $client
     */
    private $client;

    public function setUp()
    {
        $logger = $this->getMock('\Psr\Log\LoggerInterface');
        $httpClient = $this->getMock('GuzzleHttp\ClientInterface');

        $this->api = $this->getMock(
            'Martial\Transmission\API\RpcClient',
            //['sessionGet', 'torrentGet', 'torrentRemove'],
            [],
            [$httpClient, '', '']
        );
        $this->api->setLogger($logger);

        $csrfException = $this->getMock('Martial\Transmission\API\CSRFException', ['getSessionId']);
        $csrfException->method('getSessionId')->will($this->returnValue('123'));

        $this->api->method('sessionGet')->willThrowException($csrfException);
        $this->api->method('torrentGet')->will($this->returnValue($this->expectedTorrentList));

        //$this->client = $this->getMock('TransmissionClient', ['getSessionId'], [$this->api]);
        $this->client = new TransmissionClient($this->api);

        parent::setUp();
    }

    public function testGetSessionId()
    {
        $sessionId = $this->invokeMethod($this->client, 'getSessionId', ['']);
        $this->assertEquals(123, $sessionId);
    }

    /**
     * @expectedException \Martial\Transmission\API\TransmissionException
     */
    public function testGetSessionIdError()
    {
        $exception = new \Martial\Transmission\API\TransmissionException();

        $this->api->method('sessionGet')->willThrowException($exception);
        $sessionId = $this->invokeMethod($this->client, 'getSessionId', ['']);
    }

    public function testGetTorrentData()
    {
        $torrentList = $this->client->getTorrentData([1, 2]);
        $this->assertEquals($this->expectedTorrentList, $torrentList);
    }

    public function testGetTorrentsSize()
    {
        $torrentSize = $this->client->getTorrentsSize($this->expectedTorrentList);
        $this->assertEquals(7, $torrentSize);
    }

    // TODO: it asserts nothing
    public function testPrintTorrentsTable()
    {
        $output = new NullOutput();
        $this->client->printTorrentsTable($this->expectedTorrentList, $output);
    }

    public function testGetObsoleteTorrents()
    {
        $obsolete = $this->client->getObsoleteTorrents();
        $this->assertCount(1, $obsolete);
        $this->assertEquals(1, $obsolete[0]['id']);
    }

    public function testRemoveTorrents()
    {
        $result = $this->client->removeTorrents([]);
        $this->assertFalse($result);

        $result = $this->client->removeTorrents([1, 2]);
        $this->assertTrue($result);
    }
}
