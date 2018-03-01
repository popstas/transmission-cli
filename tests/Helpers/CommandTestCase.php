<?php

namespace Popstas\Transmission\Console\Tests\Helpers;

use Popstas\Transmission\Console\Application;
use Popstas\Transmission\Console\Config;
use Popstas\Transmission\Console\InfluxDbClient;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Command\Command;

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

    /**
     * @var Command
     */
    private $command;

    public function setUp()
    {
        $this->app = new Application();

        // logger
        $this->logger = $this->createMock('\Psr\Log\LoggerInterface');
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
        $httpClient = $this->createMock('GuzzleHttp\ClientInterface');
        $api = $this->getMockBuilder('Martial\Transmission\API\RpcClient')
            ->setMethods([])
            ->setConstructorArgs([$httpClient, '', ''])
            ->getMock();
        $client = $this->getMockBuilder('Popstas\Transmission\Console\TransmissionClient')
            ->setMethods([])
            ->setConstructorArgs([$api])
            ->getMock();
        $client->method('getTorrentData')->will($this->returnValue($this->expectedTorrentList));
        $this->app->setClient($client);

        // InfluxDbClient
        $influxDb = $this->getMockBuilder('InfluxDB\Client')
            ->setMethods([])
            ->setConstructorArgs([''])
            ->disableOriginalConstructor()
            ->getMock();
        $influxDbClient = new InfluxDbClient($influxDb, 'dbname');
        $database = $this->getMockBuilder('InfluxDB\Database')
            ->setMethods([])
            ->setConstructorArgs(['dbname', $influxDb])
            ->getMock();
        $queryBuilder = $this->getMockBuilder('InfluxDB\Query\Builder')
            ->setMethods(['getResultSet'])
            ->setConstructorArgs([$database])
            ->getMock();
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
