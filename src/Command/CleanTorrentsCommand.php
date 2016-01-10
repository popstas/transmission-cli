<?php

namespace Popstas\Transmission\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Martial\Transmission\API\Argument\Torrent;

class CleanTorrentsCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('clean-torrents')
            ->setDescription('Cleans torrents')
            ->setDefinition(array(
                new InputOption('host', null, InputOption::VALUE_OPTIONAL, 'Transmission host'),
            ))
            ->setHelp(<<<EOT
The <info>clean-torrents</info> removes torrents listed in text file.
EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = $this->getClient();
        $torrentList = $client->getTorrentData();

        $blacklist_file = 'blacklist.txt';

        if(!file_exists($blacklist_file)){
            $output->writeln('<error>file ' . $blacklist_file . 'not found</error>');
            exit(1);
        }

        $blackTorrentList = array_filter($torrentList, function($torrent) use ($blacklist_file){
            return $this->isTorrentBlacklisted($torrent[Torrent\Get::NAME], $blacklist_file);
        });

        $total_size = $client->getTorrentsSize($blackTorrentList);
        $size_in_gb = round($total_size / 1024 / 1000 / 1000, 2);

        $output->writeln('Black Torrent list: ' . count($blackTorrentList));
        $client->printTorrentsTable($blackTorrentList, $output);
        $output->writeln('Total size: ' . $size_in_gb . ' Gb');

        $output->writeln('<error>actual delete not implemented!</error>');
        //$client->removeTorrents($blackTorrentList, true);
    }

    private function isTorrentBlacklisted($torrent_name, $blacklist_file){
        $handle = fopen($blacklist_file, 'r') or die ('File opening failed');

        while (!feof($handle)) {
            $line = trim(fgets($handle));

            if (empty($line)) {
                continue;
            }

            if($line===$torrent_name){
                return true;
            }
        }

        return false;
    }
}