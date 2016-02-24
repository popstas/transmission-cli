<?php

namespace Popstas\Transmission\Console\Command;

use Martial\Transmission\API\Argument\Torrent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanTorrentsCommand extends Command
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('clean-torrents')
            ->setDescription('Cleans torrents')
            ->addOption('blacklist', null, InputOption::VALUE_OPTIONAL, 'Torrents blacklist')
            ->setHelp(<<<EOT
The <info>clean-torrents</info> removes torrents listed in text file.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->getApplication()->getLogger();
        $client = $this->getApplication()->getClient();

        $torrentList = $client->getTorrentData();

        $blacklist = $this->getBlacklist($input->getOption('blacklist'));

        $blackTorrentList = array_filter($torrentList, function ($torrent) use ($blacklist) {
            return in_array($torrent[Torrent\Get::NAME], $blacklist);
        });

        $total_size = $client->getTorrentsSize($blackTorrentList);
        $size_in_gb = round($total_size / 1024 / 1000 / 1000, 2);

        $output->writeln('Black Torrent list: ' . count($blackTorrentList));
        $client->printTorrentsTable($blackTorrentList, $output);
        $output->writeln('Total size: ' . $size_in_gb . ' Gb');

        if (!$input->getOption('dry-run')) {
            $logger->critical('actual delete not implemented!');
            //$client->removeTorrents($blackTorrentList, true);
        } else {
            $logger->info('dry-run, don\'t really remove');
        }
    }

    private function getBlacklist($blacklist_file)
    {
        $blacklist = [];
        if (!file_exists($blacklist_file)) {
            $this->getApplication()->getLogger()->critical('file ' . $blacklist_file . ' not found');
            return [];
        }

        $handle = fopen($blacklist_file, 'r');

        while (!feof($handle)) {
            $blacklist[] = trim(fgets($handle));
        }
        
        return $blacklist;
    }
}
