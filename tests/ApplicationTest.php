<?php

namespace Popstas\Transmission\Console\Tests;

use Popstas\Transmission\Console\Application;
use Popstas\Transmission\Console\Tests\Helpers\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;

class ApplicationTest extends TestCase
{
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
}
