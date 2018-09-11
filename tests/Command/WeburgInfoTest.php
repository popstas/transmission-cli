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
    }

    public function tearDown()
    {
        unlink($this->configFile);
        parent::tearDown();
    }

    public function testInfo()
    {
        $this->executeCommand(['movie-id' => '12345']);
        $this->assertRegExp('/Энди Уорхол/', $this->getDisplay());
    }
}
