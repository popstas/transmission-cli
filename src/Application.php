<?php

namespace Popstas\Transmission\Console;

use GuzzleHttp;
use Popstas\Transmission\Console\Command;
use Psr\Log\LoggerInterface;
use Stecman\Component\Symfony\Console\BashCompletion;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    const VERSION = 'dev';

    /**
     * @var Config $config
     */
    private $config;

    /**
     * @var LoggerInterface $logger
     */
    private $logger;

    /**
     * @var TransmissionClient $client
     */
    private $client;

    /**
     * @var WeburgClient
     */
    private $weburgClient;

    public function __construct($name = 'Transmission CLI', $version = self::VERSION)
    {
        parent::__construct($name, $version);
    }

    /**
     * @return array|\Symfony\Component\Console\Command\Command[]
     */
    protected function getDefaultCommands()
    {
        $commands = array_merge(parent::getDefaultCommands(), [
            new BashCompletion\CompletionCommand(),

            new Command\StatsSend(),
            new Command\TorrentClean(),
            new Command\TorrentList(),
            new Command\TorrentRemoveDuplicates(),
            new Command\WeburgDownload(),
            new Command\WeburgSeriesAdd(),
        ]);
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

    public function getWeburgClient()
    {
        if (!isset($this->weburgClient)) {
            $this->weburgClient = $this->createWeburgClient();
        }
        return $this->weburgClient;
    }

    public function setWeburgClient($weburgClient)
    {
        $this->weburgClient = $weburgClient;
    }

    public function createWeburgClient()
    {
        $httpClient = new GuzzleHttp\Client();
        return new WeburgClient($httpClient);
    }
}
