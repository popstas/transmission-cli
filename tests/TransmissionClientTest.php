<?php

namespace Popstas\Transmission\Console\Tests;

use Martial\Transmission\API;
use Martial\Transmission\API\Argument\Torrent;
use Popstas\Transmission\Console\Helpers\TorrentUtils;
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
        $torrentSize = TorrentUtils::getTorrentsSize($this->expectedTorrentList);
        $this->assertEquals(7, $torrentSize);
    }

    public function testGetTorrentsField()
    {
        $torrentField = TorrentUtils::getTorrentsField($this->expectedTorrentList, Torrent\Get::NAME);
        $this->assertEquals(['name.ext', 'name.ext', 'name2.ext', 'name.ext'], $torrentField);
    }

    public function testFilterTorrentsByAge()
    {
        $torrentList = [
            ['doneDate' => time() - 86400 * 0],
            ['doneDate' => time() - 86400 * 1],
            ['doneDate' => time() - 86400 * 2],
            ['doneDate' => time() - 86400 * 3],
        ];

        $this->assertEquals($torrentList, TorrentUtils::filterTorrents($torrentList, []));
        $this->assertEquals([1, 2, 3], array_keys(TorrentUtils::filterTorrents($torrentList, ['age' => '>0'])));
        $this->assertEquals([0, 1, 2], array_keys(TorrentUtils::filterTorrents($torrentList, ['age' => '< 3'])));
        $this->assertEquals([1, 2, 3], array_keys(TorrentUtils::filterTorrents($torrentList, ['age_min' => '1'])));
        $this->assertEquals([0, 1, 2], array_keys(TorrentUtils::filterTorrents($torrentList, ['age_max' => '2'])));
        $this->assertEquals([1, 2], array_keys(TorrentUtils::filterTorrents($torrentList, ['age' => '>0 < 3'])));
    }

    public function testFilterTorrentsByName()
    {
        $torrentList = [
            ['name' => 'file'],
            ['name' => 'other file'],
            ['name' => 'movie.mkv'],
            ['name' => 'Movie Season_1080p'],
        ];

        $this->assertEquals(
            [0, 1],
            array_keys(TorrentUtils::filterTorrents($torrentList, ['name' => 'file']))
        );
        $this->assertEquals(
            [0, 1, 2, 3],
            array_keys(TorrentUtils::filterTorrents($torrentList, ['name' => 'fil|mov']))
        );
        $this->assertEquals(
            [3],
            array_keys(TorrentUtils::filterTorrents($torrentList, ['name' => 'season*1080']))
        );
    }

    public function testGetTorrentAgeInDays()
    {
        // without doneDate
        $this->assertEquals(1, TorrentUtils::getTorrentAgeInDays([
            'doneDate' => 0,
            'addedDate' => time() - 86400
        ]));

        // with doneDate
        $this->assertEquals(2, TorrentUtils::getTorrentAgeInDays([
            'doneDate' => time() - 86400 * 2,
            'addedDate' => time() - 86400
        ]));
    }

    // TODO: it asserts nothing
    public function testPrintTorrentsTable()
    {
        $output = new NullOutput();
        TorrentUtils::printTorrentsTable($this->expectedTorrentList, $output);
    }

    public function testGetObsoleteTorrents()
    {
        $torrentList = $this->client->getTorrentData();
        $obsolete = TorrentUtils::getObsoleteTorrents($torrentList);
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
