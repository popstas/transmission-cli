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
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Sort by torrent name (regexp)')
            ->addOption('age', null, InputOption::VALUE_OPTIONAL, 'Sort by torrent age, ex. \'>1 <5\'')
            ->addOption('profit', null, InputOption::VALUE_OPTIONAL, 'Filter by profit')
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Show stats for last days', 7)
            ->addOption('sort', null, InputOption::VALUE_OPTIONAL, 'Sort by column number', 4)
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit torrent list')
            ->addOption('rm', null, InputOption::VALUE_NONE, 'Remove filtered torrents')
            ->addOption('soft', null, InputOption::VALUE_NONE, 'Remove only from Transmission, not delete data')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Don\'t ask confirmation')
            ->addOption('transmission-host', null, InputOption::VALUE_OPTIONAL, 'Transmission host')
            ->setHelp(<<<EOT
## Get torrents stats from InfluxDB

Command `stats-get` almost same as `torrent-list`, but it use InfluxDB:
```
transmission-cli stats-get [--name='name'] [--age='age'] [profit='>0'] [--days=7] [--sort=1] [--limit=10] [--rm]
```

You can also use `--name`, `--age`, `--sort`, `--limit`, plus `--profit` and `--days` options.

Profit = uploaded for period / torrent size. Profit metric more precise than uploaded ever value.

Show 10 worst torrents for last week:
```
transmission-cli stats-get --days 7 --profit '=0' --limit 10
```

Show stats of last added torrents sorted by profit:
```
transmission-cli stats-get --days 1 --age '<2' --sort='-7'
```


## Remove torrents

You can use command `stats-get` with `--rm` option to remove filtered unpopular torrents:
```
transmission-cli stats-get --days 7 --profit '=0' --rm
```

With `--rm` option you can use all options of `torrent-remove` command: `--soft`, `--dry-run`, `-y`.  

Without `-y` option command ask your confirmation for remove torrents.  

If you don't want to remove all filtered torrents, you can save ids of torrents and call `torrent-remove` manually.
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

            $torrentList = array_map(function ($torrent) {
                $torrent['age'] = TorrentUtils::getTorrentAgeInDays($torrent);
                return $torrent;
            }, $torrentList);

            $torrentList = TorrentListUtils::filterTorrents($torrentList, [
                'age' => $input->getOption('age'),
                'name' => $input->getOption('name'),
            ]);

            $transmissionHost = $config->overrideConfig($input, 'transmission-host');

            $torrentList = array_map(function ($torrent) use ($influxDbClient, $transmissionHost, $lastDays) {
                $torrent['uploaded'] = $influxDbClient->getTorrentSum(
                    $torrent,
                    'uploaded_last',
                    $transmissionHost,
                    $lastDays
                );

                $days = max(1, min($torrent['age'], $lastDays));
                $torrent['per_day'] = $days ?
                    TorrentUtils::getSizeInGb($torrent['uploaded'] / $days) : 0;

                $torrent['profit'] = round($torrent['uploaded'] / $torrent[Torrent\Get::TOTAL_SIZE] / $days * 100, 2);

                return $torrent;
            }, $torrentList);

            $torrentList = TorrentListUtils::filterTorrents($torrentList, [
                'profit' => ['type' => 'numeric', 'value' => $input->getOption('profit')]
            ]);


            $rows = [];

            foreach ($torrentList as $torrent) {
                $rows[] = [
                    $torrent[Torrent\Get::NAME],
                    $torrent[Torrent\Get::ID],
                    $torrent['age'],
                    TorrentUtils::getSizeInGb($torrent[Torrent\Get::TOTAL_SIZE]),
                    TorrentUtils::getSizeInGb($torrent['uploaded']),
                    $torrent['per_day'],
                    $torrent['profit']
                ];
            }
        } catch (\Exception $e) {
            $logger->critical($e->getMessage());
            return 1;
        }

        TableUtils::printTable([
            'headers' => ['Name', 'Id', 'Age, days', 'Size, GB', 'Uploaded, GB', 'Per day, GB', 'Profit, %'],
            'rows' => $rows,
            'totals' => [
                '',
                '',
                '',
                // TODO: it wrong if sort and limit applied, see https://github.com/popstas/transmission-cli/issues/21
                TorrentUtils::getSizeInGb(TorrentListUtils::sumArrayField($torrentList, Torrent\Get::TOTAL_SIZE)),
                TorrentListUtils::sumArrayField($rows, 4),
                TorrentListUtils::sumArrayField($rows, 5),
                TorrentListUtils::sumArrayField($rows, 6),
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
