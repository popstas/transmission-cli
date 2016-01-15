<?php

namespace Popstas\Transmission\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

use Martial\Transmission\API\Argument\Torrent;

class ListTorrentsCommand extends Command
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('list-torrents')
            ->setDescription('List torrents')
            ->setHelp(<<<EOT
The <info>list</info> list torrents.
EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = $this->getClient($output);
        $torrentList = $client->getTorrentData();

        $table = new Table($output);
        $table->setHeaders(['Name', 'Id', 'Size']);

        foreach($torrentList as $torrent){
            $table->addRow([
                $torrent[Torrent\Get::NAME],
                $torrent[Torrent\Get::ID],
                round($torrent[Torrent\Get::TOTAL_SIZE] / 1024 / 1000 / 1000, 2)
            ]);
        }

        $table->render();
    }
}
