<?php

namespace Popstas\Transmission\Console\Tests\Helpers;

use Popstas\Transmission\Console\Application;
use Popstas\Transmission\Console\Config;
use Symfony\Component\Console\Tester\CommandTester;

abstract class CommandTestCase extends TestCase
{
    /**
     * @var Application $app
     */
    public $app;

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

        $this->app->setLogger($this->getMock('\Psr\Log\LoggerInterface'));

        // config
        $homeDir = sys_get_temp_dir();
        $this->configFile = tempnam($homeDir, 'transmission-cli.yml');
        if (file_exists($this->configFile)) {
            unlink($this->configFile);
        }
        putenv('HOME=' . $homeDir);
        $config = new Config();
        $config->saveConfigFile();

        // weburgClient
        $httpClient = $this->getMock('GuzzleHttp\ClientInterface');
        $api = $this->getMock('Martial\Transmission\API\RpcClient', [], [$httpClient, '', '']);
        $client = $this->getMock('Popstas\Transmission\Console\TransmissionClient', [], [$api]);
        $client->method('getTorrentData')->will($this->returnValue($this->expectedTorrentList));
        $client->method('getTorrentsField')->will($this->returnValue(['a', 'b', 'c', 'd']));
        $this->app->setClient($client);

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
        $args = [ 'command' => $this->command->getName() ] + $options;
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
