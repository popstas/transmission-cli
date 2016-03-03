<?php

namespace Popstas\Transmission\Console\Command;

use Martial\Transmission\API\Argument\Torrent;
use Popstas\Transmission\Console\Helpers\TorrentUtils;
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
        $client = $this->getApplication()->getClient();

        $torrentList = $client->getTorrentData();

        $torrentList = TorrentUtils::filterTorrents($torrentList, [
            'age' => $input->getOption('age'),
            'name' => $input->getOption('name'),
        ]);

        TorrentUtils::printTorrentsTable($torrentList, $output, $input->getOption('sort'));
    }
}
