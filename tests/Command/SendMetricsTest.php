<?php

namespace Popstas\Transmission\Console\Tests\Command;

use InfluxDB;
use PHPUnit_Framework_MockObject_MockObject;
use Popstas\Transmission\Console\Config;
use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class SendMetricsTest extends CommandTestCase
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
        $this->setCommandName('send-metrics');
        parent::setUp();

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

    /**
     * @expectedException \GuzzleHttp\Exception\ConnectException;
     */
    public function testInfluxDbConnectionError()
    {
        $this->markTestIncomplete('It pass always');
        $config = new Config();
        $config->set('influxdb-host', 'null');
        $this->app->setConfig($config);

        $this->executeCommand();
    }

    public function testInfluxDbCreate()
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
