<?php

namespace Popstas\Transmission\Console\Command;

use Martial\Transmission\API\Argument\Torrent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TorrentClean extends Command
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('torrent-clean')
            ->setAliases(['tc'])
            ->setDescription('Cleans torrents')
            ->addOption('blacklist', null, InputOption::VALUE_OPTIONAL, 'Torrents blacklist')
            ->setHelp(<<<EOT
The <info>torrent-clean</info> removes torrents listed in text file.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->getApplication()->getLogger();
        $client = $this->getApplication()->getClient();

        $torrentList = $client->getTorrentData();

        try {
            $blacklist = $this->getBlacklistTorrents($input->getOption('blacklist'));
        } catch (\RuntimeException $e) {
            $logger->critical($e->getMessage());
            return 1;
        }

        $blackTorrentList = array_filter($torrentList, function ($torrent) use ($blacklist) {
            return in_array($torrent[Torrent\Get::NAME], $blacklist);
        });

        $sizeTotal = $client->getTorrentsSize($blackTorrentList);
        $sizeInGb = round($sizeTotal / 1024 / 1000 / 1000, 2);

        $output->writeln('Black Torrent list: ' . count($blackTorrentList));
        $client->printTorrentsTable($blackTorrentList, $output);
        $output->writeln('Total size: ' . $sizeInGb . ' Gb');

        if (!$input->getOption('dry-run')) {
            $logger->critical('actual delete not implemented!');
            //$client->removeTorrents($blackTorrentList, true);
        } else {
            $output->writeln('dry-run, don\'t really remove');
        }
        return 0;
    }

    /**
     * @param $blacklistFile
     * @return array torrent names
     */
    private function getBlacklistTorrents($blacklistFile)
    {
        $blacklist = [];
        if (!file_exists($blacklistFile)) {
            throw  new \RuntimeException('file ' . $blacklistFile . ' not found');
        }

        $handle = fopen($blacklistFile, 'r');

        while (!feof($handle)) {
            $blacklist[] = trim(fgets($handle));
        }
        
        return $blacklist;
    }
}
