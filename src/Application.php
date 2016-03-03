<?php

namespace Popstas\Transmission\Console;

use GuzzleHttp;
use GuzzleHttp\Exception\ConnectException;
use InfluxDB;
use Popstas\Transmission\Console\Command;
use Psr\Log\LoggerInterface;
use Stecman\Component\Symfony\Console\BashCompletion;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;

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
     * @var InfluxDB\Client $influxDb
     */
    private $influxDb;

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

            new Command\StatsSend(),
            new Command\TorrentClean(),
            new Command\TorrentList(),
            new Command\TorrentRemove(),
            new Command\TorrentRemoveDuplicates(),
            new Command\WeburgDownload(),
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
        $httpClient = new GuzzleHttp\Client();
        return new WeburgClient($httpClient, $requestDelay);
    }

    /**
     * @return InfluxDB\Client $influxDb
     */
    public function getInfluxDb()
    {
        return $this->influxDb;
    }

    /**
     * @param InfluxDB\Client $influxDb
     */
    public function setInfluxDb($influxDb)
    {
        $this->influxDb = $influxDb;
    }

    public function createInfluxDb($host, $port, $user, $password)
    {
        $logger = $this->getLogger();

        $connect = ['host' => $host, 'port' => $port, 'user' => $user, 'password' => $password];

        $influxDb = new InfluxDB\Client(
            $connect['host'],
            $connect['port'],
            $connect['user'],
            $connect['password']
        );
        $logger->debug('Connect InfluxDB using: {user}:{password}@{host}:{port}', $connect);

        return $influxDb;
    }

    /**
     * @param InputInterface $input
     * @return InfluxDB\Database
     * @throws InfluxDB\Database\Exception
     */
    public function getDatabase(InputInterface $input)
    {
        $config = $this->getConfig();
        $logger = $this->getLogger();

        $influxDb = $this->getInfluxDb();
        if (!isset($influxDb)) {
            $this->setInfluxDb($this->createInfluxDb(
                $config->overrideConfig($input, 'influxdb-host'),
                $config->overrideConfig($input, 'influxdb-port'),
                $config->overrideConfig($input, 'influxdb-user'),
                $config->overrideConfig($input, 'influxdb-password')
            ));
            $influxDb = $this->getInfluxDb();
        }

        $databaseName = $config->overrideConfig($input, 'influxdb-database');
        if (!$databaseName) {
            throw new \RuntimeException('InfluxDb database not defined');
        }

        $database = $influxDb->selectDB($databaseName);

        try {
            $databaseExists = $database->exists();
        } catch (ConnectException $e) {
            throw new \RuntimeException('InfluxDb connection error: ' . $e->getMessage());
        }
        if (!$databaseExists) {
            $logger->info('Database ' . $databaseName . ' not exists, creating');
            $database->create();
        }

        return $database;
    }
}
