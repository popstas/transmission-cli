<?php

namespace Popstas\Transmission\Console\Tests\Command;

use PHPUnit_Framework_MockObject_MockObject;
use Popstas\Transmission\Console\Config;
use Popstas\Transmission\Console\InfluxDbClient;
use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class StatsSendTest extends CommandTestCase
{
    /**
     * var InfluxDB\Client $influxDb
     * @var PHPUnit_Framework_MockObject_MockObject $influxDb
     */
    private $influxDb;

    /**
     * @var InfluxDbClient
     */
    private $influxDbClient;

    /**
     * var InfluxDB\Database $database
     * @var PHPUnit_Framework_MockObject_MockObject $database
     */
    private $database;
    private $obsoleteTorrent;

    public function setUp()
    {
        $this->setCommandName('stats-send');

        // remove obsolete torrent from test array before setUp
        $this->obsoleteTorrent = $this->expectedTorrentList[1];
        unset($this->expectedTorrentList[1]);

        parent::setUp();

        $config = new Config();
        $config->set('influxdb-host', 'localhost');
        $config->set('influxdb-port', 1234);
        $config->set('influxdb-user', 'user');
        $config->set('influxdb-password', 'pass');
        $this->app->setConfig($config);

        $this->influxDb = $this->getMockBuilder('InfluxDB\Client')
            ->setMethods([])
            ->setConstructorArgs([''])
            ->disableOriginalConstructor()
            ->getMock();

        $this->influxDbClient = $this->getMockBuilder('Popstas\Transmission\Console\InfluxDbClient')
            ->setMethods([])
            ->setConstructorArgs([$this->influxDb, 'test'])
            ->getMock();
        $this->database = $this->getMockBuilder('InfluxDB\Database')
            ->setMethods([])
            ->setConstructorArgs(['dbname', $this->influxDb])
            ->getMock();
        $this->database->method('exists')->will($this->returnValue(true));
        $this->influxDb->method('selectDB')->will($this->returnValue($this->database));

        $this->app->setInfluxDbClient($this->influxDbClient);
    }

    public function testWithoutOptions()
    {
        $result = $this->executeCommand();
        $this->assertEquals($result, 0);
    }

    public function testInfluxDbCreateInfluxDbWithoutDatabase()
    {
        $this->app->setInfluxDbClient(null);

        $config = $this->app->getConfig();
        $config->set('influxdb-database', '');
        $influx_connect = [
            'host'     => $config->get('influxdb-host'),
            'port'     => $config->get('influxdb-port'),
            'user'     => $config->get('influxdb-user'),
            'password' => $config->get('influxdb-password'),
        ];

        $logText = 'Connect InfluxDB using: {user}:{password}@{host}:{port}';
        $this->app->getLogger()->expects($this->once())->method('debug')->with(
            $this->equalTo($logText),
            $this->equalTo($influx_connect)
        );

        $result = $this->executeCommand();
        $this->assertEquals(1, $result);
        // TODO: output logger critical
        //$this->assertRegExp('/InfluxDb database not defined/', $this->getDisplay());
    }

    public function testInfluxDbCreateInfluxDb()
    {
        //$this->markTestSkipped('test freezes');
        $this->app->setInfluxDbClient(null);


        $result = $this->executeCommand();
        $this->assertEquals(1, $result);
        // TODO: output logger critical
        //$this->assertRegExp('/Could not resolve host/', $this->getDisplay());
    }

    public function testDryRun()
    {
        $this->influxDbClient->expects($this->never())->method('writePoints');
        $this->executeCommand(['--dry-run' => true]);
    }

    public function testObsoleteTorrentsExists()
    {
        $httpClient = $this->createMock('GuzzleHttp\ClientInterface');
        $api = $this->getMockBuilder('Martial\Transmission\API\RpcClient')
            ->setMethods([])
            ->setConstructorArgs([$httpClient, '', ''])
            ->getMock();
        $client = $this->getMockBuilder('Popstas\Transmission\Console\TransmissionClient')
            ->setMethods([])
            ->setConstructorArgs([$api])
            ->getMock();
        // put back obsolete torrent
        $this->expectedTorrentList[1] = $this->obsoleteTorrent;
        $client->method('getTorrentData')->will($this->returnValue($this->expectedTorrentList));
        $this->app->setClient($client);

        //$this->influxDb->expects($this->never())->method('getInfluxDb');
        $this->executeCommand();
        $this->assertRegExp('/Found obsolete torrents/', $this->getDisplay());
    }
}
