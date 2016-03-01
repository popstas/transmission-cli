<?php

namespace Popstas\Transmission\Console\Command;

use GuzzleHttp\Exception\ConnectException;
use InfluxDB;
use Martial\Transmission\API\Argument\Torrent;
use Popstas\Transmission\Console\Helpers\TorrentUtils;
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

        $torrentList = $client->getTorrentData();
        $obsoleteList = TorrentUtils::getObsoleteTorrents($torrentList);
        if (!empty($obsoleteList)) {
            $output->writeln('<comment>Found obsolete torrents,
                              remove it using transmission-cli torrent-remove-duplicates</comment>');
            return 1;
        }

        try {
            $database = $this->getDatabase($input);
        } catch (\Exception $e) {
            $logger->critical($e->getMessage());
            return 1;
        }

        $points = [];

        $torrentList = $client->getTorrentData();

        $transmissionHost = $config->overrideConfig($input, 'transmission-host');

        foreach ($torrentList as $torrent) {
            $point = new InfluxDB\Point(
                'uploaded',
                $torrent[Torrent\Get::UPLOAD_EVER],
                [
                    'host'         => $transmissionHost,
                    'torrent_name' => $torrent[Torrent\Get::NAME],
                ],
                [],
                time()
            );
            $points[] = $point;
            $logger->debug('Send point: {point}', ['point' => $point]);
        }

        $this->dryRun($input, $output, function () use ($database, $points, $logger) {
            $isSuccess = $database->writePoints($points, InfluxDB\Database::PRECISION_SECONDS);
            $logger->info('InfluxDB write ' . ($isSuccess ? 'success' : 'failed'));
        }, 'dry-run, don\'t really send points');

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
    private function getDatabase(InputInterface $input)
    {
        $config = $this->getApplication()->getConfig();
        $logger = $this->getApplication()->getLogger();

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
