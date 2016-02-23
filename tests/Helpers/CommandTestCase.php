<?php

namespace Popstas\Transmission\Console\Tests\Helpers;

use Popstas\Transmission\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

abstract class CommandTestCase extends TestCase
{
    /**
     * @var Application $app
     */
    public $app;

    private $commandName;

    /**
     * @var CommandTester
     */
    private $commandTester;

    public function setUp()
    {
        $this->app = new Application();

        $this->app->setLogger($this->getMock('\Psr\Log\LoggerInterface'));

        $httpClient = $this->getMock('GuzzleHttp\ClientInterface');
        $api = $this->getMock('Martial\Transmission\API\RpcClient', [], [$httpClient, '', '']);

        $client = $this->getMock('Popstas\Transmission\Console\TransmissionClient', [], [$api]);
        $client->method('getTorrentData')->will($this->returnValue($this->expectedTorrentList));

        $this->app->setClient($client);
        parent::setUp();
    }

    public function setCommandName($name)
    {
        $this->commandName = $name;
    }

    public function executeCommand($options = [])
    {
        $command = $this->app->find($this->commandName);
        $this->commandTester = new CommandTester($command);
        $args = [ 'command' => $command->getName() ] + $options;
        $this->commandTester->execute($args);
    }
    
    public function getDisplay()
    {
        return $this->commandTester->getDisplay();
    }
}
