<?php

namespace Popstas\Transmission\Console;

use GuzzleHttp;
use InfluxDB;
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

    /**
     * @var InfluxDbClient
     */
    private $influxDbClient;

    public function __construct($name = 'Transmission CLI', $version = '@git-version@')
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

            new Command\Docs(),
            new Command\StatsGet(),
            new Command\StatsSend(),
            new Command\TorrentAdd(),
            new Command\TorrentList(),
            new Command\TorrentRemove(),
            new Command\TorrentRemoveDuplicates(),
            new Command\WeburgDownload(),
            new Command\WeburgInfo(),
            new Command\WeburgSeriesAdd(),
        ]);
        return $commands;
    }

    public function getLongVersion()
    {
        if (('@' . 'git-version@') !== $this->getVersion()) {
            return sprintf(
                '<info>%s</info> version <comment>%s</comment> build <comment>%s</comment>',
                $this->getName(),
                $this->getVersion(),
                '@git-commit@'
            );
        }
        return '<info>' . $this->getName() . '</info> (repo)';
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
        $config = $this->getConfig();
        $requestDelay = $config->get('weburg-request-delay');
        $httpClient = new GuzzleHttp\Client([
            'allow_redirects' => [
                'max'             => 10,
                'strict'          => false,
                'referer'         => false,
                'protocols'       => ['http', 'https'],
                'track_redirects' => false,
            ],
        ]);
        return new WeburgClient($httpClient, $requestDelay);
    }

    /**
     * @param $host
     * @param $port
     * @param $user
     * @param $password
     * @param $databaseName
     * @return InfluxDbClient
     * @throws InfluxDB\Database\Exception
     */
    public function getInfluxDbClient($host, $port, $user, $password, $databaseName)
    {
        if (!isset($this->influxDbClient)) {
            $this->influxDbClient = $this->createInfluxDbClient($host, $port, $user, $password, $databaseName);
        }
        return $this->influxDbClient;
    }

    /**
     * @param InfluxDbClient $influxDbClient
     */
    public function setInfluxDbClient($influxDbClient)
    {
        $this->influxDbClient = $influxDbClient;
    }

    /**
     * @param $host
     * @param $port
     * @param $user
     * @param $password
     * @param $databaseName
     * @return InfluxDbClient
     * @throws InfluxDB\Database\Exception
     */
    public function createInfluxDbClient($host, $port, $user, $password, $databaseName)
    {
        $influxDb = new InfluxDB\Client($host, $port, $user, $password);
        $connect = ['host' => $host, 'port' => $port, 'user' => $user, 'password' => $password];
        $this->logger->debug('Connect InfluxDB using: {user}:{password}@{host}:{port}', $connect);

        if (!$databaseName) {
            throw new \RuntimeException('InfluxDb database not defined');
        }

        $influxDbClient = new InfluxDbClient($influxDb, $databaseName);
        $influxDbClient->setLogger($this->logger);

        return $influxDbClient;
    }
}
