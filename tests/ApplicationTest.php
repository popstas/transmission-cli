<?php

namespace Popstas\Transmission\Console\Tests;

use Popstas\Transmission\Console\Application;
use Popstas\Transmission\Console\Config;
use Popstas\Transmission\Console\Tests\Helpers\CommandTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;

class ApplicationTest extends CommandTestCase
{
    public function setUp()
    {
        $this->setCommandName('torrent-list');
        parent::setUp();
    }

    public function testGetVersion()
    {
        $app = new Application();
        $app->setAutoExit(false);
        $input = new ArrayInput(array('--version'));
        $stream = fopen('php://memory', 'w', false);
        $output = new StreamOutput($stream);
        $app->run($input, $output);
        rewind($stream);
        $string = trim(fgets($stream));
        $string = preg_replace(
            array(
                '/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/',
                '/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/',
                '/[\x03|\x1a]/'
            ),
            array('', '', ''),
            $string
        );
        $this->assertEquals('Transmission CLI (repo)', $string);

        $app->setVersion('1.2.3');
        rewind($stream);
        $app->run($input, $output);
        rewind($stream);
        $string = trim(fgets($stream));
        $string = preg_replace(
            array(
                '/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/',
                '/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/',
                '/[\x03|\x1a]/'
            ),
            array('', '', ''),
            $string
        );
        $this->assertEquals(
            'Transmission CLI version 1.2.3 build @git-commit@',
            $string
        );
    }

    public function testDefaultConfigWriteOnAppStart()
    {
        $configFile = Config::getHomeDir() . '/.transmission-cli.yml';
        if (file_exists($configFile)) {
            unlink($configFile);
        }

        $this->executeCommand();
        $this->assertFileExists($configFile);
    }
}
