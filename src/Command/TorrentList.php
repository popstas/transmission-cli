<?php

namespace Popstas\Transmission\Console\Command;

use Martial\Transmission\API\Argument\Torrent;
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

        $torrentList = $client->filterTorrents($torrentList, [
            'age' => $input->getOption('age'),
        ]);

        $rows = [];
        foreach ($torrentList as $torrent) {
            $age = $client->getTorrentAgeInDays($torrent);
            $perDay = $age ? round($torrent[Torrent\Get::UPLOAD_EVER] / $age / 1024 / 1000 / 1000, 2) : 0;

            $rows[] = [
                $torrent[Torrent\Get::NAME],
                $torrent[Torrent\Get::ID],
                $age,
                round($torrent[Torrent\Get::DOWNLOAD_EVER] / 1024 / 1000 / 1000, 2),
                round($torrent[Torrent\Get::UPLOAD_EVER] / 1024 / 1000 / 1000, 2),
                $perDay,
            ];
        }

        $totals = [
            'Total',
            '',
            '',
            round($client->getTorrentsSize($torrentList) / 1024 / 1000 / 1000, 2),
            round($client->getTorrentsSize($torrentList, Torrent\Get::UPLOAD_EVER) / 1024 / 1000 / 1000, 2),
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
