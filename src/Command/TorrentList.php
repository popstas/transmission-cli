<?php

namespace Popstas\Transmission\Console\Command;

use Martial\Transmission\API\Argument\Torrent;
use Popstas\Transmission\Console\Helpers\TorrentUtils;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TorrentList extends Command
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('torrent-list')
            ->setAliases(['tl'])
            ->setDescription('List torrents')
            ->addOption('sort', null, InputOption::VALUE_OPTIONAL, 'Sort by column number', 4)
            ->addOption('age', null, InputOption::VALUE_OPTIONAL, 'Sort by torrent age, ex. \'>1 <5\'')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Sort by torrent name (regexp)')
            ->setHelp(<<<EOT
The <info>torrent-list</info> list torrents.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $headers = ['Name', 'Id', 'Age', 'Size', 'Uploaded', 'Per day'];

        $client = $this->getApplication()->getClient();

        $torrentList = $client->getTorrentData();

        $torrentList = TorrentUtils::filterTorrents($torrentList, [
            'age' => $input->getOption('age'),
            'name' => $input->getOption('name'),
        ]);

        $rows = [];
        foreach ($torrentList as $torrent) {
            $age = TorrentUtils::getTorrentAgeInDays($torrent);
            $perDay = $age ? TorrentUtils::getSizeInGb($torrent[Torrent\Get::UPLOAD_EVER] / $age) : 0;

            $rows[] = [
                $torrent[Torrent\Get::NAME],
                $torrent[Torrent\Get::ID],
                $age,
                TorrentUtils::getSizeInGb($torrent[Torrent\Get::DOWNLOAD_EVER]),
                TorrentUtils::getSizeInGb($torrent[Torrent\Get::UPLOAD_EVER]),
                $perDay,
            ];
        }

        $totals = [
            'Total',
            '',
            '',
            TorrentUtils::getSizeInGb(TorrentUtils::getTorrentsSize($torrentList)),
            TorrentUtils::getSizeInGb(TorrentUtils::getTorrentsSize($torrentList, Torrent\Get::UPLOAD_EVER)),
            ''
        ];

        $rows = $this->sortTable($rows, $input);

        $table = new Table($output);
        $table->setHeaders($headers);
        $table->setRows($rows);
        $table->addRow(new TableSeparator());
        $table->addRow($totals);
        $table->render();
    }

    private function sortTable(array $rows, InputInterface $input)
    {
        $rowsSorted = $rows;
        $columnsTotal = count(end($rows));

        $sortColumn = max(1, min(
            $columnsTotal,
            $input->getOption('sort')
        )) - 1;

        usort($rowsSorted, function ($first, $second) use ($sortColumn) {
            return $first[$sortColumn] > $second[$sortColumn] ? 1 : -1;
        });

        return $rowsSorted;
    }
}
