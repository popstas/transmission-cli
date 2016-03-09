<?php

namespace Popstas\Transmission\Console\Tests;

use Martial\Transmission\API;
use Martial\Transmission\API\Argument\Session;
use Martial\Transmission\API\Argument\Torrent;
use Popstas\Transmission\Console\Tests\Helpers\TestCase;
use Popstas\Transmission\Console\TransmissionClient;

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
    private $logger;
    private $httpClient;

    public function setUp()
    {
        $this->logger = $this->getMock('\Psr\Log\LoggerInterface');
        $this->httpClient = $this->getMock('GuzzleHttp\ClientInterface');

        $this->api = $this->getMock('Martial\Transmission\API\RpcClient', [], [$this->httpClient, '', '']);
        $this->api->setLogger($this->logger);

        $this->csrfException = $this->getMock('Martial\Transmission\API\CSRFException', ['getSessionId']);
        $this->csrfException->method('getSessionId')->will($this->returnValue('123'));

        $this->api->method('sessionGet')->willThrowException($this->csrfException);
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
        //$this->markTestIncomplete();
    }

    public function testGetTorrentData()
    {
        $torrentList = $this->client->getTorrentData([1, 2]);
        $this->assertEquals($this->expectedTorrentList, $torrentList);
    }

    public function testAddFile()
    {
        $this->client->addTorrent('/abc.torrent');
        $this->client->addTorrent('/abc.torrent', '/path/to/download');
        //$this->markTestIncomplete();
    }

    public function testAddMetadata()
    {
        $torrentFile = tempnam(sys_get_temp_dir(), 'torrent');
        $this->client->addTorrent($torrentFile);
        unlink($torrentFile);
        //$this->markTestIncomplete();
    }

    public function testRemoveTorrents()
    {
        $result = $this->client->removeTorrents([]);
        $this->assertFalse($result);

        $result = $this->client->removeTorrents([1, 2]);
        $this->assertTrue($result);
    }

    public function testWaitForTransmission()
    {
        $this->client->waitForTransmission(0);
    }

    public function testGetDownloadDir()
    {
        $this->api = $this->getMock(
            'Martial\Transmission\API\RpcClient',
            ['sessionGet', 'getSessionId', 'freeSpace'],
            [$this->httpClient, '', '']
        );
        $this->api->setLogger($this->logger);
        $this->api->method('sessionGet')->willReturn([
            Session\Get::DOWNLOAD_DIR => '/a/b/c'
        ]);
        $this->client = new TransmissionClient($this->api);

        $this->assertEquals('/a/b/c', $this->client->getDownloadDir());
    }

    public function testGetFreeSpace()
    {
        $this->api = $this->getMock(
            'Martial\Transmission\API\RpcClient',
            ['sessionGet', 'getSessionId', 'freeSpace'],
            [$this->httpClient, '', '']
        );
        $this->api->method('sessionGet')->willReturn([
            Session\Get::DOWNLOAD_DIR => '/a/b/c'
        ]);
        $this->client = new TransmissionClient($this->api);

        $this->api->expects($this->once())
            ->method('freeSpace')->with(null, '/a/b/c')->willReturn(['arguments' => ['size-bytes' => 12345]]);
        $this->assertEquals(12345, $this->client->getFreeSpace());
    }

    public function testGetFreeSpaceWithDir()
    {
        $this->api = $this->getMock(
            'Martial\Transmission\API\RpcClient',
            ['sessionGet', 'getSessionId', 'freeSpace'],
            [$this->httpClient, '', '']
        );
        $this->api->method('sessionGet')->willReturn([
            Session\Get::DOWNLOAD_DIR => '/a/b/c'
        ]);
        $this->client = new TransmissionClient($this->api);

        $this->api->expects($this->once())
            ->method('freeSpace')->with(null, '/d/e/f')->willReturn(['arguments' => ['size-bytes' => 12345]]);
        $this->assertEquals(12345, $this->client->getFreeSpace('/d/e/f'));
    }
}
