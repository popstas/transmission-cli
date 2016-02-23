<?php

namespace Popstas\Transmission\Console\Tests\Helpers;

use Popstas\Transmission\Console\Application;

abstract class CommandTestCase extends TestCase
{
    /**
     * @var Application $app
     */
    public $app;

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
}
