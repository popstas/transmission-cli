<?php

namespace Popstas\Transmission\Console\Tests;

use InfluxDB;
use Popstas\Transmission\Console\InfluxDbClient;
use Popstas\Transmission\Console\Tests\Helpers\TestCase;

class InfluxDbClientTest extends TestCase
{
    /**
     * @var InfluxDbClient $client
     */
    private $client;

    /**
     * @var InfluxDB\Client
     */
    private $influxDb;

    public function setUp()
    {
        $this->influxDb = $this->getMockBuilder('InfluxDB\Client')
            ->setMethods([])
            ->setConstructorArgs([''])
            ->disableOriginalConstructor()
            ->getMock();

        $this->client = new InfluxDbClient($this->influxDb, 'dbname');

        $logger = $this->getMock('\Psr\Log\LoggerInterface');
        $this->client->setLogger($logger);

        parent::setUp();
    }

    private function getDatabaseMockReturnsResultSet($value)
    {
        $database = $this->getMock('InfluxDB\Database', [], ['dbname', $this->influxDb]);

        $queryBuilder = $this->getMock('InfluxDB\Query\Builder', ['getResultSet'], [$database]);
        $resultSet = $this->getMockBuilder('InfluxDB\ResultSet')
            ->setMethods([])
            ->setConstructorArgs([''])
            ->disableOriginalConstructor()
            ->getMock();
        $resultSet->method('getPoints')->willReturn($value);
        $queryBuilder->method('getResultSet')->will($this->returnValue($resultSet));
        $database->method('getQueryBuilder')->willReturn($queryBuilder);

        return $database;
    }

    public function testConnectDatabaseOnGet()
    {
        $this->client = $this->getMock(
            'Popstas\Transmission\Console\InfluxDbClient',
            ['connectDatabase'],
            [$this->influxDb, 'dbname']
        );
        $database = $this->getMock('InfluxDB\Database', [], ['dbname', $this->influxDb]);
        $this->client->expects($this->once())->method('connectDatabase')->willReturn($database);


        $this->client->writePoints([]);
    }

    public function testConnectExistsDatabase()
    {
        $database = $this->getMock('InfluxDB\Database', [], ['dbname', $this->influxDb]);
        $database->method('exists')->will($this->returnValue(true));
        $this->influxDb->method('selectDB')->will($this->returnValue($database));

        $this->client->setDatabase($this->client->connectDatabase());
        $this->client->connectDatabase();
    }

    public function testConnectNotExistsDatabase()
    {
        $database = $this->getMock('InfluxDB\Database', [], ['dbname', $this->influxDb]);
        $database->method('exists')->will($this->returnValue(false));
        $database->expects($this->once())->method('create');
        $this->influxDb->method('selectDB')->will($this->returnValue($database));

        $this->client->connectDatabase();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testConnectBrokenDatabase()
    {
        $database = $this->getMock('InfluxDB\Database', [], ['dbname', $this->influxDb]);

        $requestInterface = $this->getMock('Psr\Http\Message\RequestInterface');
        $exception = new \GuzzleHttp\Exception\ConnectException('error', $requestInterface);
        $database->method('exists')->willThrowException($exception);

        $this->influxDb->method('selectDB')->will($this->returnValue($database));

        $this->client->connectDatabase();
    }

    public function testBuildPoint()
    {
        $database = $this->getDatabaseMockReturnsResultSet([]);
        $this->client->setDatabase($database);

        $torrent = $this->expectedTorrentList[0];
        $this->client->buildPoint($torrent, 'localhost');
    }

    public function testSendTorrentPoints()
    {
        $database = $this->getDatabaseMockReturnsResultSet([]);
        $this->client->setDatabase($database);
        $this->client->sendTorrentPoints($this->expectedTorrentList, 'localhost');
    }

    public function testWritePoints()
    {
        $database = $this->getMock('InfluxDB\Database', [], ['dbname', $this->influxDb]);
        $database->method('writePoints')->willReturn(true);
        $this->client->setDatabase($database);

        $this->client->writePoints([]);
    }


    public function testGetTorrentSum()
    {
        $database = $this->getDatabaseMockReturnsResultSet([['uploaded_last' => 123]]);
        $this->client->setDatabase($database);
        $this->assertEquals(
            123,
            $this->client->getTorrentSum($this->expectedTorrentList[0], 'uploaded_last', 'localhost', 7)
        );

        $database = $this->getDatabaseMockReturnsResultSet([]);
        $this->client->setDatabase($database);
        $this->assertEquals(
            0,
            $this->client->getTorrentSum($this->expectedTorrentList[0], 'uploaded_last', 'localhost', 7)
        );
    }
}
