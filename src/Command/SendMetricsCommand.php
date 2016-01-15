<?php

namespace Popstas\Transmission\Console\Command;

use InfluxDB;
use Martial\Transmission\API\Argument\Torrent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendMetricsCommand extends Command
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('send-metrics')
            ->setDescription('Send metrics to InfluxDB')
            ->setHelp(<<<EOT
The <info>send-metrics</info> sends upload ever for every torrent to InfluxDB.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $influx_connect = [
            'host'     => $this->config->get('influxdb-host'),
            'port'     => $this->config->get('influxdb-port'),
            'user'     => $this->config->get('influxdb-user'),
            'password' => $this->config->get('influxdb-password'),
            'database' => $this->config->get('influxdb-database'),
        ];

        $transmission_host = $this->config->get('transmission-host');

        $logger = $this->getLogger($output);

        $logger->debug('Connect InfluxDB using: {user}:{password}@{host}:{port}', $influx_connect);

        $client = $this->getClient($output);
        $obsoleteList = $client->getObsoleteTorrents();
        if (!empty($obsoleteList)) {
            $output->writeln('<comment>Found obsolete torrents,
                              remove it using transmission-cli remove-duplicates</comment>');
            exit(1);
        }

        $influxdb = new InfluxDB\Client(
            $influx_connect['host'],
            $influx_connect['port'],
            $influx_connect['user'],
            $influx_connect['password']
        );
        $database = $influxdb->selectDB($influx_connect['database']);

        $points = [];

        if (!$database->exists()) {
            $logger->info('Database ' . $influx_connect['database'] . ' not exists, creating');
            $database->create();
        }

        $torrentList = $client->getTorrentData();

        foreach ($torrentList as $torrent) {
            $point = new InfluxDB\Point(
                'uploaded',
                $torrent[Torrent\Get::UPLOAD_EVER],
                [
                    'host'         => $transmission_host,
                    'torrent_name' => $torrent[Torrent\Get::NAME],
                ],
                [],
                time()
            );
            $points[] = $point;
            $logger->debug('Send point: {point}', ['point' => $point]);
        }

        if (!$input->getOption('dry-run')) {
            $isSuccess = $database->writePoints($points, InfluxDB\Database::PRECISION_SECONDS);
            $logger->info('InfluxDB write ' . ($isSuccess ? 'success' : 'failed'));
        } else {
            $logger->info('dry-run, don\'t really send points');
        }

    }
}
