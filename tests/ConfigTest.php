<?php

namespace Popstas\Transmission\Console\Tests;

use InvalidArgumentException;
use Popstas\Transmission\Console\Config;
use Popstas\Transmission\Console\Application;
use Popstas\Transmission\Console\Tests\Helpers\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ConfigTest extends TestCase
{
    public function testDefaultConfigWrite()
    {
        $homeDir = sys_get_temp_dir();
        $configFile = $homeDir . '/.transmission-cli.yml';
        putenv('HOME=' . $homeDir);

        unlink($configFile);

        $config = new Config();
        $config->loadConfigFile();

        $this->assertTrue(file_exists($configFile));

        unlink($configFile);
    }

    public function testSaveLoadConfig()
    {
        $config = new Config();
        $config->set('param', 'value');
        $this->assertEquals($config->get('param'), 'value');

        $configFile = tempnam(sys_get_temp_dir(), 'config');
        $config->saveConfigFile($configFile);

        $config->set('param', 'valueChanged');
        $config->loadConfigFile($configFile);
        $this->assertEquals($config->get('param'), 'value');

        unlink($configFile);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testNonExistConfig()
    {
        $config = new Config();
        $config->loadConfigFile('/a/b/c/transmission-cli.yml');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConfigAsOption()
    {
        $application = new Application();

        $command = $application->find('list-torrents');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'   => $command->getName(),
            '--config'  => '/a/b/c/transmission-cli.yml',
        ));
    }

    public function testGet()
    {
        $config = new Config();
        $this->assertNull($config->get('non-existent-parameter'));
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
