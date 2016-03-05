<?php

namespace Popstas\Transmission\Console\Command;

use InfluxDB;
use Martial\Transmission\API\Argument\Torrent;
use Popstas\Transmission\Console\Helpers\TableUtils;
use Popstas\Transmission\Console\Helpers\TorrentListUtils;
use Popstas\Transmission\Console\Helpers\TorrentUtils;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StatsGet extends Command
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('stats-get')
            ->setAliases(['sg'])
            ->setDescription('Get metrics from InfluxDB')
            ->addOption('sort', null, InputOption::VALUE_OPTIONAL, 'Sort by column number', 4)
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Sort by torrent name (regexp)')
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Show stats for last days', 7)
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit torrent list')
            ->addOption('profit', null, InputOption::VALUE_OPTIONAL, 'Filter by profit')
            ->addOption('rm', null, InputOption::VALUE_NONE, 'Remove filtered torrents')
            ->addOption('soft', null, InputOption::VALUE_NONE, 'Remove only from Transmission, not delete data')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Don\'t ask confirmation')
            ->addOption('transmission-host', null, InputOption::VALUE_OPTIONAL, 'Transmission host')
            ->setHelp(<<<EOT
The <info>stats-get</info> sends upload ever for every torrent to InfluxDB.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $this->getApplication()->getConfig();
        $logger = $this->getApplication()->getLogger();
        $client = $this->getApplication()->getClient();

        try {
            $influxDbClient = $this->getApplication()->getInfluxDbClient(
                $config->get('influxdb-host'),
                $config->get('influxdb-port'),
                $config->get('influxdb-user'),
                $config->get('influxdb-password'),
                $config->get('influxdb-database')
            );

            $lastDays = (int)$input->getOption('days') ? (int)$input->getOption('days') : 0;
            $limit = (int)$input->getOption('limit') ? (int)$input->getOption('limit') : 0;

            $torrentList = $client->getTorrentData();

            $torrentList = TorrentListUtils::filterTorrents($torrentList, [
                'name' => $input->getOption('name'),
            ]);

            $transmissionHost = $config->overrideConfig($input, 'transmission-host');

            $rows = [];

            foreach ($torrentList as $torrent) {
                $uploaded = $influxDbClient->getTorrentSum($torrent, 'uploaded_last', $transmissionHost, $lastDays);

                $profit = round($uploaded / $torrent[Torrent\Get::TOTAL_SIZE], 2);

                $rows[] = [
                    $torrent[Torrent\Get::NAME],
                    $torrent[Torrent\Get::ID],
                    TorrentUtils::getSizeInGb($uploaded) . ' GB',
                    $profit
                ];
            }
        } catch (\Exception $e) {
            $logger->critical($e->getMessage());
            return 1;
        }

        $rows = TableUtils::filterRows($rows, [
            '3' => ['type' => 'numeric', 'value' => $input->getOption('profit')]
        ]);

        TableUtils::printTable([
            'headers' => ['Name', 'Id', 'Uploaded', 'Profit'],
            'rows' => $rows,
            'totals' => [
                '',
                '',
                TorrentListUtils::sumArrayField($rows, 2),
                TorrentListUtils::sumArrayField($rows, 3)
            ]
        ], $output, $input->getOption('sort'), $limit);

        if ($input->getOption('rm')) {
            return $this->removeTorrents($input, $output, $rows);
        }

        return 0;
    }

    private function removeTorrents(InputInterface $input, OutputInterface $output, array $rows)
    {
        $limit = (int)$input->getOption('limit') ? (int)$input->getOption('limit') : 0;

        $rows = TableUtils::sortRowsByColumnNumber($rows, $input->getOption('sort'));

        if ($limit && $limit < count($rows)) {
            $rows = array_slice($rows, 0, $limit);
        }

        $torrentIds = TorrentListUtils::getArrayField($rows, 1);
        $command = $this->getApplication()->find('torrent-remove');
        $arguments = array(
            'command'     => 'torrent-remove',
            'torrent-ids' => $torrentIds,
            '--dry-run'   => $input->getOption('dry-run'),
            '--yes'       => $input->getOption('yes'),
            '--soft'      => $input->getOption('soft'),
        );

        $removeInput = new ArrayInput($arguments);
        return $command->run($removeInput, $output);
    }
}
