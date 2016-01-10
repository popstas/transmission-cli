<?php

namespace Popstas\Transmission\Console\Command;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Martial\Transmission\API;
use Popstas\Transmission\Console;
use Popstas\Transmission\Console\Config;

class Command extends BaseCommand{
    protected $config;
    private $client;

    public function __construct($name = null) {
        $this->config = new Config();
        parent::__construct($name);
    }

    protected function initialize(InputInterface $input, OutputInterface $output) {
        if($input->hasOption('host') && $input->getOption('host')){
            $this->config->set('transmission-host', $input->getOption('host'));
        }
        parent::initialize($input, $output);
    }

    protected function getClient() {
        if(isset($this->client)){
            return $this->client;
        }

        $host = $this->config->get('transmission-host');
        $port = $this->config->get('transmission-port');
        $username = $this->config->get('transmission-username');
        $password = $this->config->get('transmission-password');

        $this->client = new Console\TransmissionClient($host, $port, $username, $password);

        return $this->client;
    }
}