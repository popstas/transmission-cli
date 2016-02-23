<?php

namespace Popstas\Transmission\Console\Command;

use GuzzleHttp;
use Martial\Transmission\API;
use Popstas\Transmission\Console;
use Popstas\Transmission\Console\Application;
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
    protected function configure()
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run, don\'t change any data');
        $this->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Transmission host');
        $this->addOption('config', null, InputOption::VALUE_OPTIONAL, 'Configuration file');
    }

    /**
     * @return Application
     */
    public function getApplication()
    {
        return parent::getApplication();
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        // logger
        $logger = $this->getApplication()->getLogger();
        if (!isset($logger)) {
            $verbosityLevelMap = [
                LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
                LogLevel::INFO   => OutputInterface::VERBOSITY_VERBOSE,
                LogLevel::DEBUG  => OutputInterface::VERBOSITY_DEBUG,
            ];
            $logger = new ConsoleLogger($output, $verbosityLevelMap);
            $this->getApplication()->setLogger($logger);
        }

        // config
        $config = $this->getApplication()->getConfig();
        if (!isset($config)) {
            $config = new Config($input->getOption('config'));
            if ($input->hasOption('host') && $input->getOption('host')) {
                $config->set('transmission-host', $input->getOption('host'));
            }
            $this->getApplication()->setConfig($config);
        }

        // client
        $client = $this->getApplication()->getClient();
        if (!isset($client)) {
            $this->getApplication()->setClient($this->createClient($config, $logger));
        }

        $logger->info('[{date}] command: {args}', [
            'date' => date('Y-m-d H:i:s'),
            'args' => implode(' ', array_slice($_SERVER['argv'], 1)),
        ]);

        parent::initialize($input, $output);
    }

    private function createClient(Config $config, ConsoleLogger $logger)
    {
        $connect = [
            'host'     => $config->get('transmission-host'),
            'port'     => $config->get('transmission-port'),
            'user'     => $config->get('transmission-user'),
            'password' => $config->get('transmission-password'),
        ];

        $logger->debug('Connect Transmission using: {user}:{password}@{host}:{port}', $connect);

        $base_uri = 'http://' . $connect['host'] . ':' . $connect['port'] . '/transmission/rpc';
        $httpClient = new GuzzleHttp\Client(['base_uri' => $base_uri]);

        $api = new API\RpcClient($httpClient, $connect['user'], $connect['password']);
        $api->setLogger($logger);

        return new TransmissionClient($api);
    }
}
