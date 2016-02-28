<?php

namespace Popstas\Transmission\Console\Command;

use GuzzleHttp\Exception\ConnectException;
use InfluxDB;
use Martial\Transmission\API\Argument\Torrent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StatsSend extends Command
{
    /**
     * @var InfluxDB\Client $influxDb
     */
    private $influxDb;

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('stats-send')
            ->setAliases(['ss'])
            ->setDescription('Send metrics to InfluxDB')
            ->addOption('influxdb-host', null, InputOption::VALUE_OPTIONAL, 'InfluxDb host')
            ->addOption('influxdb-port', null, InputOption::VALUE_OPTIONAL, 'InfluxDb port')
            ->addOption('influxdb-user', null, InputOption::VALUE_OPTIONAL, 'InfluxDb user')
            ->addOption('influxdb-password', null, InputOption::VALUE_OPTIONAL, 'InfluxDb password')
            ->addOption('influxdb-database', null, InputOption::VALUE_OPTIONAL, 'InfluxDb database')
            ->addOption('transmission-host', null, InputOption::VALUE_OPTIONAL, 'Transmission host')
            ->setHelp(<<<EOT
The <info>stats-send</info> sends upload ever for every torrent to InfluxDB.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $this->getApplication()->getConfig();
        $logger = $this->getApplication()->getLogger();
        $client = $this->getApplication()->getClient();

        $obsoleteList = $client->getObsoleteTorrents();
        if (!empty($obsoleteList)) {
            $output->writeln('<comment>Found obsolete torrents,
                              remove it using transmission-cli torrent-remove-duplicates</comment>');
            return 1;
        }

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

        $database_name = $config->overrideConfig($input, 'influxdb-database');
        if (!$database_name) {
            $output->writeln('InfluxDb database not defined');
            return 1;
        }

        $database = $influxDb->selectDB($database_name);

        $points = [];

        try {
            $databaseExists = $database->exists();
        } catch (ConnectException $e) {
            $logger->critical('InfluxDb connection error: ' . $e->getMessage());
            return 1;
        }
        if (!$databaseExists) {
            $logger->info('Database ' . $database_name . ' not exists, creating');
            $database->create();
        }

        $torrentList = $client->getTorrentData();

        $transmission_host = $config->overrideConfig($input, 'transmission-host');

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

        return 0;
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
        $logger = $this->getApplication()->getLogger();

        $influx_connect = ['host' => $host, 'port' => $port, 'user' => $user, 'password' => $password];

        $influxDb = new InfluxDB\Client(
            $influx_connect['host'],
            $influx_connect['port'],
            $influx_connect['user'],
            $influx_connect['password']
        );
        $logger->debug('Connect InfluxDB using: {user}:{password}@{host}:{port}', $influx_connect);

        return $influxDb;
    }
}
