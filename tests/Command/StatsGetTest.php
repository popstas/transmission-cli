<?php

namespace Popstas\Transmission\Console\Tests\Command;

use InfluxDB;
use PHPUnit_Framework_MockObject_MockObject;
use Popstas\Transmission\Console\Config;
use Popstas\Transmission\Console\InfluxDbClient;
use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class StatsGetTest extends CommandTestCase
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

    public function setUp()
    {
        $this->setCommandName('stats-get');

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

        $this->influxDbClient = $this->getMock(
            'Popstas\Transmission\Console\InfluxDbClient',
            [],
            [$this->influxDb, 'test']
        );
        $this->database = $this->getMock('InfluxDB\Database', null, ['dbname', $this->influxDb]);
        $this->database->method('exists')->will($this->returnValue(true));
        $this->influxDb->method('selectDB')->will($this->returnValue($this->database));

        $this->app->setInfluxDbClient($this->influxDbClient);
    }

    // TODO: it copy-paste of test StatsSendTest::testInfluxDbCreateInfluxDbWithoutDatabase
    public function testInfluxDbCreateInfluxDbWithoutDatabase()
    {
        $this->app->setInfluxDbClient(null);

        $config = $this->app->getConfig();
        $config->set('influxdb-database', '');
        $influx_connect = [
            'host' => $config->get('influxdb-host'),
            'port' => $config->get('influxdb-port'),
            'user' => $config->get('influxdb-user'),
            'password' => $config->get('influxdb-password')
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

    public function testWithoutArguments()
    {
        $this->executeCommand();
    }

    public function testRemoveNotConfirmed()
    {
        $command = $this->getCommand();

        $question = $this->getMock('Symfony\Component\Console\Helper\QuestionHelper', ['ask']);
        $question->expects($this->at(0))
            ->method('ask')
            ->will($this->returnValue(false));

        $command->getHelperSet()->set($question, 'question');

        $result = $this->executeCommand([
            '--rm'    => true,
            '--limit' => 1
        ]);
        $this->assertEquals(1, $result);
        $this->assertRegExp('/Aborting/', $this->getDisplay());
    }
}
