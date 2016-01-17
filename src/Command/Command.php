<?php

namespace Popstas\Transmission\Console\Command;

use Martial\Transmission\API;
use Popstas\Transmission\Console;
use Popstas\Transmission\Console\Config;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class Command extends BaseCommand
{
    protected $config;
    private $client;

    public function __construct($name = null)
    {
        $this->config = new Config();
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run, don\'t change any data');
        $this->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Transmission host');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->getLogger($output);

        $logger->info('[{date}] command: {args}', [
            'date' => date('Y-m-d H:i:s'),
            'args' => implode(' ', array_slice($_SERVER['argv'], 1)),
        ]);

        if ($input->hasOption('host') && $input->getOption('host')) {
            $this->config->set('transmission-host', $input->getOption('host'));
        }

        parent::initialize($input, $output);
    }

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

        $this->client = new Console\TransmissionClient(
            $logger,
            $connect['host'],
            $connect['port'],
            $connect['user'],
            $connect['password']
        );

        return $this->client;
    }
}
