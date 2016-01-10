<?php

namespace Popstas\Transmission\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
//use Symfony\Component\Console\Helper\Table;

use Martial\Transmission\API\Argument\Torrent;
use InfluxDB;

class SendMetricsCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('send-metrics')
            ->setDescription('Send metrics to InfluxDB')
            ->setDefinition(array(
                new InputOption('host', null, InputOption::VALUE_OPTIONAL, 'Transmission host'),
            ))
            ->setHelp(<<<EOT
The <info>send-metrics</info> sends upload ever for every torrent to InfluxDB.
EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $host = $this->config->get('influxdb-host');
        $port = $this->config->get('influxdb-port');
        $user = $this->config->get('influxdb-user');
        $password = $this->config->get('influxdb-password');
        $database_name = $this->config->get('influxdb-database');

        $transmission_host = $this->config->get('transmission-host');

        $client = $this->getClient();
        $obsoleteList = $client->getObsoleteTorrents();
        if(!empty($obsoleteList)){
            $output->writeln('<comment>Found obsolete torrents, remove it using transmission-cli remove-duplicates</comment>');
            exit(1);
        }

        $influxdb = new InfluxDB\Client($host, $port, $user, $password);
        $database = $influxdb->selectDB($database_name);

        $points = [];

        if(!$database->exists()){
            $output->writeln('<info>Database '.$database_name.'not exists, creating</info>');
            $database->create();
        }

        $torrentList = $client->getTorrentData();

        //$table = new Table($output);

        foreach ($torrentList as $torrent) {
            $point = new InfluxDB\Point(
                'uploaded',
                $torrent[Torrent\Get::UPLOAD_EVER],
                ['host' => $transmission_host, 'torrent_name' => $torrent[Torrent\Get::NAME]],
                [],
                time()
            );
            $points[] = $point;

            /*$table->addRow([
                $torrent[Torrent\Get::NAME],
                $torrent[Torrent\Get::UPLOAD_EVER],
                $transmission_host,
                time()
            ]);*/
        }

        //$table->render();
        $database->writePoints($points, InfluxDB\Database::PRECISION_SECONDS);
    }
}