<?php

namespace Popstas\Transmission\Console\Command;

use Martial\Transmission\API\Argument\Torrent;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
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
            ->setHelp(<<<EOT
The <info>torrent-list</info> list torrents.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = $this->getApplication()->getClient();

        $torrentList = $client->getTorrentData();

        $table = new Table($output);
        $table->setHeaders(['Name', 'Id', 'Size']);

        foreach ($torrentList as $torrent) {
            $table->addRow([
                $torrent[Torrent\Get::NAME],
                $torrent[Torrent\Get::ID],
                round($torrent[Torrent\Get::TOTAL_SIZE] / 1024 / 1000 / 1000, 2),
            ]);
        }

        $table->addRow(new TableSeparator());

        $table->addRow([
            'Total',
            '',
            round($client->getTorrentsSize($torrentList) / 1024 / 1000 / 1000, 2)
        ]);

        $table->render();
    }
}
