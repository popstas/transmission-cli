<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Config;
use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class CommandTest extends CommandTestCase
{
    public function setUp()
    {
        $this->setCommandName('torrent-list');
        parent::setUp();
    }

    /**
     * @expectedException Martial\Transmission\API\TransmissionException
     */
    public function testCreateTransmissionClientError()
    {
        $this->app->setClient(null);

        $config = new Config();
        $config->set('transmission-host', 'devnull');
        $config->set('transmission-port', 1234);
        $config->set('transmission-user', 'user');
        $config->set('transmission-password', 'pass');
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
