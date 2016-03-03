<?php

namespace Popstas\Transmission\Console\Command;

use Martial\Transmission\API\Argument\Torrent;
use Popstas\Transmission\Console\Helpers\TorrentListUtils;
use Popstas\Transmission\Console\Helpers\TorrentUtils;
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

        $sizeTotal = TorrentListUtils::getTorrentsSize($blackTorrentList);
        $sizeInGb = TorrentUtils::getSizeInGb($sizeTotal);

        $output->writeln('Black Torrent list: ' . count($blackTorrentList));
        TorrentListUtils::printTorrentsTable($blackTorrentList, $output);
        $output->writeln('Total size: ' . $sizeInGb . ' Gb');

        $this->dryRun($input, $output, function () use ($logger) {
            $logger->critical('actual delete not implemented!');
            //$client->removeTorrents($blackTorrentList, true);
        }, 'dry-run, don\'t really remove');

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
