<?php

namespace Popstas\Transmission\Console\Command;

use InfluxDB;
use Martial\Transmission\API\Argument\Torrent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendMetricsCommand extends Command
{
    /**
     * @var InfluxDB\Client $influxDb
     */
    private $influxDb;

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
        $config = $this->getApplication()->getConfig();
        $logger = $this->getApplication()->getLogger();
        $client = $this->getApplication()->getClient();

        $transmission_host = $config->get('transmission-host');

        $obsoleteList = $client->getObsoleteTorrents();
        if (!empty($obsoleteList)) {
            $output->writeln('<comment>Found obsolete torrents,
                              remove it using transmission-cli remove-duplicates</comment>');
            return 1;
        }

        $influxDb = $this->getInfluxDb();
        $database_name = $config->get('influxdb-database');
        $database = $influxDb->selectDB($database_name);

        $points = [];

        if (!$database->exists()) {
            $logger->info('Database ' . $database_name . ' not exists, creating');
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

    /**
     * @return mixed
     */
    public function getInfluxDb()
    {
        if (!isset($this->influxDb)) {
            $config = $this->getApplication()->getConfig();
            $logger = $this->getApplication()->getLogger();

            $influx_connect = [
                'host'     => $config->get('influxdb-host'),
                'port'     => $config->get('influxdb-port'),
                'user'     => $config->get('influxdb-user'),
                'password' => $config->get('influxdb-password'),
            ];

            $this->influxDb = new InfluxDB\Client(
                $influx_connect['host'],
                $influx_connect['port'],
                $influx_connect['user'],
                $influx_connect['password']
            );
            $logger->debug('Connect InfluxDB using: {user}:{password}@{host}:{port}', $influx_connect);
        }
        return $this->influxDb;
    }

    /**
     * @param mixed $influxDb
     */
    public function setInfluxDb($influxDb)
    {
        $this->influxDb = $influxDb;
    }
}
