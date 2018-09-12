<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Config;
use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class WeburgInfoTest extends CommandTestCase
{
    private $configFile;

    public function setUp()
    {
        $this->setCommandName('weburg-info');
        parent::setUp();

        $this->configFile = tempnam(sys_get_temp_dir(), 'config');
        $config = new Config($this->configFile);
        $config->saveConfigFile();

        $this->app->setConfig($config);

        $httpClient = $this->createMock('GuzzleHttp\ClientInterface');
        $client = $this->getMockBuilder('Popstas\Transmission\Console\WeburgClient')
            ->setMethods(['getMovieInfoById'])
            ->setConstructorArgs([$httpClient])
            ->getMock();
        $client->method('getMovieInfoById')->willReturn(['title' => 'movie', 'comments' => 123, 'rating_imdb' => null]);

        $this->app->setWeburgClient($client);
    }

    public function tearDown()
    {
        unlink($this->configFile);
        parent::tearDown();
    }

    public function testInfo()
    {
        $this->executeCommand(['movie-id' => '12345']);
        $this->assertRegExp('/movie/', $this->getDisplay());
    }
}
