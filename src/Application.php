<?php

namespace Popstas\Transmission\Console;

use Popstas\Transmission\Console\Command;
use Psr\Log\LoggerInterface;
use Stecman\Component\Symfony\Console\BashCompletion;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    const VERSION = 'dev';

    /**
     * @var LoggerInterface $logger
     */
    private $logger;

    /**
     * @var TransmissionClient $client
     */
    private $client;

    /**
     * @var Config $config
     */
    private $config;

    public function __construct($name = 'Transmission CLI', $version = self::VERSION)
    {
        parent::__construct($name, $version);
    }

    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();

        $commands[] = new Command\CleanTorrentsCommand();
        $commands[] = new Command\ListTorrentsCommand();

        $commands[] = new Command\StatsSend();
        $commands[] = new Command\TorrentRemoveDuplicates();
        $commands[] = new Command\WeburgDownload();

        $commands[] = new BashCompletion\CompletionCommand();

        return $commands;
    }

    /**
     * @return TransmissionClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param TransmissionClient $client
     */
    public function setClient($client)
    {
        $this->client = $client;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param Config $config
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }
}
