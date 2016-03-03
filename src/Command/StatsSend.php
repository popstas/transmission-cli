<?php

namespace Popstas\Transmission\Console\Command;

use InfluxDB;
use Martial\Transmission\API\Argument\Torrent;
use Popstas\Transmission\Console\Helpers\TorrentUtils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StatsSend extends Command
{
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
            $database = $this->getApplication()->getDatabase($input);
        } catch (\Exception $e) {
            $logger->critical($e->getMessage());
            return 1;
        }

        $points = [];

        $torrentList = $client->getTorrentData();

        $transmissionHost = $config->overrideConfig($input, 'transmission-host');

        foreach ($torrentList as $torrent) {
            $age = TorrentUtils::getTorrentAge($torrent);
            $tagsData = [
                'host'             => $transmissionHost,
                'torrent_name'     => $torrent[Torrent\Get::NAME],
                'downloaded'       => $torrent[Torrent\Get::DOWNLOAD_EVER],
                'age'              => $age,
                'uploaded_per_day' => $age ? $torrent[Torrent\Get::UPLOAD_EVER] / $age * 86400 : 0
            ];

            $point = new InfluxDB\Point('uploaded', $torrent[Torrent\Get::UPLOAD_EVER], $tagsData, [], time());

            if ($age) {
                $points[] = $point;
                $logger->debug('Send point: {point}', ['point' => $point]);
            } else {
                $logger->debug('Skip point: {point}', ['point' => $point]);
            }
        }

        $this->dryRun($input, $output, function () use ($database, $points, $logger) {
            $isSuccess = $database->writePoints($points, InfluxDB\Database::PRECISION_SECONDS);
            $logger->info('InfluxDB write ' . ($isSuccess ? 'success' : 'failed'));
        }, 'dry-run, don\'t really send points');

        return 0;
    }
}
