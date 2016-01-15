<?php

namespace Popstas\Transmission\Console\Command;

use Martial\Transmission\API\Argument\Torrent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanTorrentsCommand extends Command
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('clean-torrents')
            ->setDescription('Cleans torrents')
            ->setHelp(<<<EOT
The <info>clean-torrents</info> removes torrents listed in text file.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->getLogger($output);

        $client = $this->getClient($output);
        $torrentList = $client->getTorrentData();

        $blacklist_file = getcwd() . '/blacklist.txt';

        if (!file_exists($blacklist_file)) {
            $logger->critical('file ' . $blacklist_file . ' not found');
            exit(1);
        }

        $blackTorrentList = array_filter($torrentList, function ($torrent) use ($blacklist_file) {
            return $this->isTorrentBlacklisted($torrent[Torrent\Get::NAME], $blacklist_file);
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

    private function isTorrentBlacklisted($torrent_name, $blacklist_file)
    {
        $handle = fopen($blacklist_file, 'r') or die('File opening failed');

        while (!feof($handle)) {
            $line = trim(fgets($handle));

            if (empty($line)) {
                continue;
            }

            if ($line === $torrent_name) {
                return true;
            }
        }

        return false;
    }
}
