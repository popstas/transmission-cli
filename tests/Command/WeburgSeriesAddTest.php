<?php

namespace Popstas\Transmission\Console\Tests\Command;

use Popstas\Transmission\Console\Config;
use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;

class WeburgSeriesAddTest extends CommandTestCase
{
    private $configFile;

    public function setUp()
    {
        $this->setCommandName('weburg-series-add');
        parent::setUp();

        $this->configFile = tempnam(sys_get_temp_dir(), 'config');
        $config = new Config($this->configFile);
        $config->saveConfigFile();

        $this->app->setConfig($config);
    }

    public function tearDown()
    {
        unlink($this->configFile);
        parent::tearDown();
    }

    public function testAddTwice()
    {
        $this->executeCommand(['series-id' => '12345']);
        $this->assertRegExp('/Series 12345 added to list/', $this->getDisplay());

        $this->executeCommand(['series-id' => 'http://weburg.net/series/info/12345']);
        $this->assertRegExp('/already in list/', $this->getDisplay());
    }

    public function testAddInvalidUrl()
    {
        $this->executeCommand(['series-id' => 'http://invalid-url/12345']);
        $this->assertRegExp('/http:\/\/invalid-url\/12345 seems not weburg series url/', $this->getDisplay());
    }
}
