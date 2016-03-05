<?php

namespace Popstas\Transmission\Console\Tests\Helpers;

use Popstas\Transmission\Console\Application;
use Popstas\Transmission\Console\Config;
use Popstas\Transmission\Console\InfluxDbClient;
use Symfony\Component\Console\Tester\CommandTester;

abstract class CommandTestCase extends TestCase
{
    /**
     * @var Application $app
     */
    public $app;

    /**
     * @var \Psr\Log\LoggerInterface $logger
     */
    public $logger;

    private $commandName;

    private $configFile;

    /**
     * @var CommandTester
     */
    private $commandTester;

    private $command;

    public function setUp()
    {
        $this->app = new Application();

        // logger
        $this->logger = $this->getMock('\Psr\Log\LoggerInterface');
        $this->app->setLogger($this->logger);

        // config
        $homeDir = sys_get_temp_dir();
        $this->configFile = tempnam($homeDir, 'transmission-cli.yml');
        if (file_exists($this->configFile)) {
            unlink($this->configFile);
        }
        putenv('HOME=' . $homeDir);
        $config = new Config();
        $config->saveConfigFile();

        // TransmissionClient
        $httpClient = $this->getMock('GuzzleHttp\ClientInterface');
        $api = $this->getMock('Martial\Transmission\API\RpcClient', [], [$httpClient, '', '']);
        $client = $this->getMock('Popstas\Transmission\Console\TransmissionClient', [], [$api]);
        $client->method('getTorrentData')->will($this->returnValue($this->expectedTorrentList));
        $this->app->setClient($client);

        // InfluxDbClient
        $influxDb = $this->getMockBuilder('InfluxDB\Client')
            ->setMethods([])
            ->setConstructorArgs([''])
            ->disableOriginalConstructor()
            ->getMock();
        $influxDbClient = new InfluxDbClient($influxDb, 'dbname');
        $database = $this->getMock('InfluxDB\Database', [], ['dbname', $influxDb]);
        $queryBuilder = $this->getMock('InfluxDB\Query\Builder', ['getResultSet'], [$database]);
        $resultSet = $this->getMockBuilder('InfluxDB\ResultSet')
            ->setMethods([])
            ->setConstructorArgs([''])
            ->disableOriginalConstructor()
            ->getMock();
        $resultSet->method('getPoints')->willReturn([]);
        $queryBuilder->method('getResultSet')->will($this->returnValue($resultSet));
        $database->method('getQueryBuilder')->willReturn($queryBuilder);
        $influxDbClient->setDatabase($database);
        $this->app->setInfluxDbClient($influxDbClient);

        $this->command = $this->app->find($this->commandName);
        $this->commandTester = new CommandTester($this->command);

        parent::setUp();
    }

    public function tearDown()
    {
        if (file_exists($this->configFile)) {
            unlink($this->configFile);
        }
        parent::tearDown();
    }

    public function setCommandName($name)
    {
        $this->commandName = $name;
    }

    public function executeCommand($options = [])
    {
        $args = ['command' => $this->command->getName()] + $options;
        return $this->commandTester->execute($args);
    }

    /**
     * @return mixed
     */
    public function getCommand()
    {
        return $this->command;
    }

    public function getDisplay()
    {
        return $this->commandTester->getDisplay();
    }
}
