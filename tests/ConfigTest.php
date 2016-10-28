<?php

namespace Popstas\Transmission\Console\Tests;

use Popstas\Transmission\Console\Config;
use Popstas\Transmission\Console\Tests\Helpers\TestCase;

class ConfigTest extends TestCase
{
    private $configFile;

    public function tearDown()
    {
        if (file_exists($this->configFile)) {
            unlink($this->configFile);
        }
        parent::tearDown();
    }

    public function testDefaultConfigWrite()
    {
        $homeDir = sys_get_temp_dir();
        $configFile = $homeDir . '/.transmission-cli.yml';
        putenv('HOME=' . $homeDir);

        if (file_exists($configFile)) {
            unlink($configFile);
        }

        $config = new Config(); // creates config on default path
        $config->saveConfigFile();

        $this->assertFileExists($configFile);

        unlink($configFile);
    }

    public function testDefaultConfigRead()
    {
        $config = new Config();
        $config->set('param', 'value');
        $config->saveConfigFile();

        $config = new Config();
        $config->loadConfigFile();
        $this->assertEquals('value', $config->get('param'));
    }

    public function testSaveLoadConfig()
    {
        $homeDir = sys_get_temp_dir();
        $configFile = $homeDir . '/.transmission-cli.yml';
        putenv('HOME=' . $homeDir);

        $config = new Config($configFile);
        $config->set('param', 'value');
        $this->assertEquals('value', $config->get('param'));

        $config->saveConfigFile();

        $config->set('param', 'valueChanged');
        $config->loadConfigFile();
        $this->assertEquals('value', $config->get('param'));

        unlink($configFile);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testNonExistConfig()
    {
        $config = new Config('/a/b/c/transmission-cli.yml');
        $config->loadConfigFile();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testCorruptedConfig()
    {
        $this->configFile = tempnam(sys_get_temp_dir(), 'config');
        $config = new Config($this->configFile);
        $config->loadConfigFile();
    }

    public function testGet()
    {
        $config = new Config();
        $this->assertNull($config->get('non-existent-parameter'));
    }

    public function testOverrideConfig()
    {
        $config = new Config();
        $config->set('config-parameter', 'original');

        // not defined option
        $input = $this->getMock('Symfony\Component\Console\Input\InputInterface');
        $input->method('hasOption')->willReturn(false);
        $input->expects($this->never())->method('getOption');
        $config->overrideConfig($input, 'config-parameter');
        $this->assertEquals('original', $config->get('config-parameter'));

        // override with option name = config name
        $input = $this->getMock('Symfony\Component\Console\Input\InputInterface');
        $input->method('hasOption')->willReturn(true);
        $input->method('getOption')->willReturn('overrided');
        $config->overrideConfig($input, 'config-parameter');
        $this->assertEquals('overrided', $config->get('config-parameter'));

        // override with option name != config name
        $input = $this->getMock('Symfony\Component\Console\Input\InputInterface');
        $input->method('hasOption')->willReturn(true);
        $input->method('getOption')->willReturn('overrided2');
        $config->overrideConfig($input, 'option-name', 'config-parameter');
        $this->assertEquals('overrided2', $config->get('config-parameter'));
    }

    public function testGetHomeDir()
    {
        $home = getenv('HOME');

        putenv('HOME=/home/user');
        $this->assertEquals('/home/user', Config::getHomeDir());

        putenv('HOME=');
        $_SERVER['HOMEDRIVE'] = 'c:';
        $_SERVER['HOMEPATH'] = '\\server\\directory\\';
        $this->assertEquals('c:\\server\\directory', Config::getHomeDir());

        putenv('HOME=' . $home);
    }
}
