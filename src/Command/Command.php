<?php

namespace Popstas\Transmission\Console\Command;

use GuzzleHttp;
use Martial\Transmission\API;
use Popstas\Transmission\Console;
use Popstas\Transmission\Console\Config;
use Popstas\Transmission\Console\TransmissionClient;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class Command extends BaseCommand
{
    /**
     * @var Config $config
     */
    protected $config;

    /**
     * @var TransmissionClient $client
     */
    private $client;

    protected function configure()
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run, don\'t change any data');
        $this->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Transmission host');
        $this->addOption('config', null, InputOption::VALUE_OPTIONAL, 'Configuration file');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->getLogger($output);

        $logger->info('[{date}] command: {args}', [
            'date' => date('Y-m-d H:i:s'),
            'args' => implode(' ', array_slice($_SERVER['argv'], 1)),
        ]);

        $this->config = new Config();
        $this->config->loadConfigFile($input->getOption('config'));

        if ($input->hasOption('host') && $input->getOption('host')) {
            $this->config->set('transmission-host', $input->getOption('host'));
        }

        parent::initialize($input, $output);
    }

    /**
     * @param OutputInterface $output
     * @return ConsoleLogger
     */
    protected function getLogger(OutputInterface $output)
    {
        $verbosityLevelMap = [
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO   => OutputInterface::VERBOSITY_VERBOSE,
            LogLevel::DEBUG  => OutputInterface::VERBOSITY_DEBUG,
        ];
        return new ConsoleLogger($output, $verbosityLevelMap);
    }

    protected function getClient(OutputInterface $output)
    {
        if (isset($this->client)) {
            return $this->client;
        }

        $connect = [
            'host'     => $this->config->get('transmission-host'),
            'port'     => $this->config->get('transmission-port'),
            'user'     => $this->config->get('transmission-user'),
            'password' => $this->config->get('transmission-password'),
        ];

        $logger = $this->getLogger($output);
        $logger->debug('Connect Transmission using: {user}:{password}@{host}:{port}', $connect);

        $base_uri = 'http://' . $connect['host'] . ':' . $connect['port'] . '/transmission/rpc';
        $httpClient = new GuzzleHttp\Client(['base_uri' => $base_uri]);

        $api = new API\RpcClient($httpClient, $connect['user'], $connect['password']);
        $api->setLogger($logger);

        $this->client = new TransmissionClient($api);

        return $this->client;
    }
}
