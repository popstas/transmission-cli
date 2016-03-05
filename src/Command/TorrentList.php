<?php

namespace Popstas\Transmission\Console\Command;

use Martial\Transmission\API\Argument\Torrent;
use Popstas\Transmission\Console\Helpers\TorrentListUtils;
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
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Sort by torrent name (regexp)')
            ->addOption('age', null, InputOption::VALUE_OPTIONAL, 'Sort by torrent age, ex. \'>1 <5\'')
            ->addOption('sort', null, InputOption::VALUE_OPTIONAL, 'Sort by column number', 4)
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit torrent list')
            ->setHelp(<<<EOT
The <info>torrent-list</info> list torrents.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = $this->getApplication()->getClient();

        $torrentList = $client->getTorrentData();

        $torrentList = TorrentListUtils::filterTorrents($torrentList, [
            'age' => $input->getOption('age'),
            'name' => $input->getOption('name'),
        ]);

        TorrentListUtils::printTorrentsTable(
            $torrentList,
            $output,
            $input->getOption('sort'),
            $input->getOption('limit')
        );
    }
}
