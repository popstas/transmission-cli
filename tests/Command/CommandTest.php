<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Application;
use Popstas\Transmission\Console\Config;
use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CommandTest extends CommandTestCase
{
    public function setUp()
    {
        $this->setCommandName('torrent-list');
        parent::setUp();
    }

    /**
     * test only for cover Application::getConfig and Command::initialize
     */
    public function testWithoutAnyMock()
    {
        $app = new Application();
        $command = $app->find('torrent-list');
        $commandTester = new CommandTester($command);
        $result = $commandTester->execute(['command' => 'torrent-list', '--config' => 'a/b/c/nonexists']);
        $this->assertEquals(1, $result);
    }

    public function testNonExistConfigAsOption()
    {
        $result = $this->executeCommand(['--config'  => '/a/b/c/transmission-cli.yml']);
        // TODO: check message 'Config file not found'
        $this->assertEquals(1, $result);
    }

    public function testCorruptedConfigAsOption()
    {
        $configFile = tempnam(sys_get_temp_dir(), 'config');
        $result = $this->executeCommand(['--config'  => $configFile]);
        // TODO: check message 'Config file corrupted'
        $this->assertEquals(1, $result);
        unlink($configFile);
    }

    /**
     * @expectedException \Martial\Transmission\API\TransmissionException
     */
    public function testCreateTransmissionClientError()
    {
        $this->app->setClient(null);

        $config = new Config();
        $config->set('transmission', [[
            'host' => 'devnull',
            'port' => 1234,
            'user' => 'user',
            'password' => 'pass'
        ]]);
        $this->app->setConfig($config);

        /*$connect = [
            'host' => $config->get('transmission-host'),
            'port' => $config->get('transmission-port'),
            'user' => $config->get('transmission-user'),
            'password' => $config->get('transmission-password')
        ];

        $logText = 'Connect Transmission using: {user}:{password}@{host}:{port}';
        $this->app->getLogger()->expects($this->at(2))->method('debug')->with(
            $this->equalTo($logText),
            $this->equalTo($connect)
        );*/

        $result = $this->executeCommand();
        $this->assertEquals(1, $result);
    }

    public function testCreateTransmissionClientSuccess()
    {
        $this->setCommandName('weburg-download');
        parent::setUp();

        $this->app->setClient(null);

        $config = new Config();
        $config->set('transmission-host', 'devnull');
        $config->set('transmission-port', 1234);
        $config->set('transmission-user', 'user');
        $config->set('transmission-password', 'pass');
        $config->set('dest', '');
        $this->app->setConfig($config);

        $result = $this->executeCommand();
        $this->assertEquals(1, $result);
        $this->assertRegExp('/Destination directory not defined/', $this->getDisplay());
    }
}
