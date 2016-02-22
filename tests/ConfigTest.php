<?php

namespace Popstas\Transmission\Console\Tests;

use InvalidArgumentException;
use Popstas\Transmission\Console\Config;
use Popstas\Transmission\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ConfigTest extends \PHPUnit_Framework_TestCase
{
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
}
