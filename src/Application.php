<?php

namespace Popstas\Transmission\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Stecman\Component\Symfony\Console\BashCompletion;
use Popstas\Transmission\Console\Command;


class Application extends BaseApplication {

    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();
        $commands[] = new Command\CleanTorrentsCommand();
        $commands[] = new Command\DownloadWeburgCommand();
        $commands[] = new Command\ListTorrentsCommand();
        $commands[] = new Command\RemoveDuplicatesCommand();
        $commands[] = new Command\SendMetricsCommand();

        $commands[] = new BashCompletion\CompletionCommand();

        return $commands;
    }
}