<?php

namespace Popstas\Transmission\Console\Command;

use GuzzleHttp;
use InfluxDB;
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
    private $help;

    protected function configure()
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run, don\'t change any data');
        $this->addOption('config', null, InputOption::VALUE_OPTIONAL, 'Configuration file');
        $this->addOption('transmission-host', null, InputOption::VALUE_OPTIONAL, 'Transmission host');
        $this->addOption('transmission-port', null, InputOption::VALUE_OPTIONAL, 'Transmission port');
        $this->addOption('transmission-user', null, InputOption::VALUE_OPTIONAL, 'Transmission user');
        $this->addOption('transmission-password', null, InputOption::VALUE_OPTIONAL, 'Transmission password');
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
            try {
                $config->loadConfigFile();
            } catch (\RuntimeException $e) {
                $logger->critical($e->getMessage());
                $this->setCode(function () {
                    return 1;
                });
                return;
            }
            $this->getApplication()->setConfig($config);
        }

        // client
        $client = $this->getApplication()->getClient();
        if (!isset($client)) {
            $client = $this->createTransmissionClient(
                $config->overrideConfig($input, 'transmission-host'),
                $config->overrideConfig($input, 'transmission-port'),
                $config->overrideConfig($input, 'transmission-user'),
                $config->overrideConfig($input, 'transmission-password')
            );
            $this->getApplication()->setClient($client);
        }

        $logger->info('[{date}] command: {args}', [
            'date' => date('Y-m-d H:i:s'),
            'args' => implode(' ', array_slice($_SERVER['argv'], 1)),
        ]);

        parent::initialize($input, $output);
    }

    private function createTransmissionClient($host, $port, $user, $password)
    {
        $logger = $this->getApplication()->getLogger();

        $connect = ['host' => $host, 'port' => $port, 'user' => $user, 'password' => $password];

        $baseUri = 'http://' . $connect['host'] . ':' . $connect['port'] . '/transmission/rpc';
        $httpClient = new GuzzleHttp\Client(['base_uri' => $baseUri]);

        $api = new API\RpcClient($httpClient, $connect['user'], $connect['password']);
        $api->setLogger($logger);

        $logger->debug('Connect Transmission using: {user}:{password}@{host}:{port}', $connect);

        return new TransmissionClient($api);
    }

    public function dryRun(
        InputInterface $input,
        OutputInterface $output,
        \Closure $callback,
        $message = 'dry-run, don\'t doing anything'
    ) {
        if (!$input->getOption('dry-run')) {
            return $callback();
        } else {
            $output->writeln($message);
            return true;
        }
    }

    public function setHelp($help)
    {
        $this->help = $help;
        $help = preg_replace('/```\n(.*?)\n```/mis', '<info>$1</info>', $help);
        $help = preg_replace('/(\n|^)#+ (.*)/', '$1<question>$2</question>', $help);
        $help = preg_replace('/`([^`]*?)`/', '<comment>$1</comment>', $help);
        $help = preg_replace('/\*\*(.*?)\*\*/', '<comment>$1</comment>', $help);
        return parent::setHelp($help);
    }
    
    public function getRawHelp()
    {
        return $this->help;
    }
}
