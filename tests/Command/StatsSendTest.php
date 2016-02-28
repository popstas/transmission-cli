<?php

namespace Popstas\Transmission\Console\Tests\Command;

use InfluxDB;
use PHPUnit_Framework_MockObject_MockObject;
use Popstas\Transmission\Console\Config;
use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class StatsSendTest extends CommandTestCase
{
    /**
     * var InfluxDB\Client $influxDb
     * @var PHPUnit_Framework_MockObject_MockObject $influxDb
     */
    private $influxDb;

    /**
     * var InfluxDB\Database $database
     * @var PHPUnit_Framework_MockObject_MockObject $database
     */
    private $database;

    public function setUp()
    {
        $this->setCommandName('stats-send');
        parent::setUp();

        $config = new Config();
        $config->set('influxdb-host', 'devnull');
        $config->set('influxdb-port', 1234);
        $config->set('influxdb-user', 'user');
        $config->set('influxdb-password', 'pass');
        $this->app->setConfig($config);

        $this->influxDb = $this->getMockBuilder('InfluxDB\Client')
         ->setMethods([])
         ->setConstructorArgs([''])
         ->disableOriginalConstructor()
         ->getMock();

        $this->database = $this->getMock('InfluxDB\Database', [], ['dbname', $this->influxDb]);
        $this->database->method('exists')->will($this->returnValue(true));
        $this->influxDb->method('selectDB')->will($this->returnValue($this->database));

        $this->getCommand()->setInfluxDb($this->influxDb);
    }

    public function testWithoutOptions()
    {
        $this->executeCommand();
    }

    public function testInfluxDbCreateInfluxDbWithoutDatabase()
    {
        $this->getCommand()->setInfluxDb(null);

        $config = $this->app->getConfig();
        $config->set('influxdb-database', '');
        $influx_connect = [
            'host' => $config->get('influxdb-host'),
            'port' => $config->get('influxdb-port'),
            'user' => $config->get('influxdb-user'),
            'password' => $config->get('influxdb-password')
        ];

        $logText = 'Connect InfluxDB using: {user}:{password}@{host}:{port}';
        $this->app->getLogger()->expects($this->at(1))->method('debug')->with(
            $this->equalTo($logText),
            $this->equalTo($influx_connect)
        );

        $result = $this->executeCommand();
        $this->assertEquals(1, $result);
        $this->assertRegExp('/InfluxDb database not defined/', $this->getDisplay());
    }

    public function testInfluxDbConnectionError()
    {
        $requestInterface = $this->getMock('Psr\Http\Message\RequestInterface');
        $exception = new \GuzzleHttp\Exception\ConnectException('error', $requestInterface);

        $this->influxDb = $this->getMockBuilder('InfluxDB\Client')
            ->setMethods([])
            ->setConstructorArgs([''])
            ->disableOriginalConstructor()
            ->getMock();

        $database = $this->getMock('InfluxDB\Database', ['exists'], ['dbname', $this->influxDb]);
        $database->method('exists')->willThrowException($exception);
        $this->influxDb->method('selectDB')->will($this->returnValue($database));
        $this->getCommand()->setInfluxDb($this->influxDb);

        $this->app->getLogger()->expects($this->once())->method('critical');
        $result = $this->executeCommand();
        // TODO: output log errors as real app
        //$this->assertRegExp('/InfluxDb connection error/', $this->getDisplay());
        $this->assertEquals(1, $result);
    }

    public function testInfluxDbCreateDatabase()
    {
        $this->influxDb = $this->getMockBuilder('InfluxDB\Client')
            ->setMethods([])
            ->setConstructorArgs([''])
            ->disableOriginalConstructor()
            ->getMock();

        $this->database = $this->getMock('InfluxDB\Database', [], ['dbname', $this->influxDb]);
        $this->database->method('exists')->will($this->returnValue(false));
        $this->database->expects($this->once())->method('create');
        $this->influxDb->method('selectDB')->will($this->returnValue($this->database));

        $this->getCommand()->setInfluxDb($this->influxDb);
        $this->executeCommand();
    }

    public function testDryRun()
    {
        $this->database->expects($this->never())->method('writePoints');
        $this->executeCommand(['--dry-run' => true]);
    }

    public function testObsoleteTorrentsExists()
    {
        $this->app->getClient()->method('getObsoleteTorrents')->will($this->returnValue($this->expectedTorrentList));
        $this->influxDb->expects($this->never())->method('getInfluxDb');
        $this->executeCommand();
        $this->assertRegExp('/Found obsolete torrents/', $this->getDisplay());
    }
}
