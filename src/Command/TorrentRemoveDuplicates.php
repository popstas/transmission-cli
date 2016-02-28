<?php

namespace Popstas\Transmission\Console\Command;

use Martial\Transmission\API\Argument\Torrent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TorrentRemoveDuplicates extends Command
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('torrent-remove-duplicates')
            ->setAliases(['trd'])
            ->setDescription('Remove duplicates obsolete torrents')
            ->setHelp(<<<EOT
The <info>torrent-remove-duplicates</info> removed all torrents with same name and smaller size than other.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->getApplication()->getLogger();
        $client = $this->getApplication()->getClient();

        $obsoleteList = $client->getObsoleteTorrents();
        if (empty($obsoleteList)) {
            $output->writeln('There are no obsolete torrents in Transmission.');
            return 0;
        }

        if (!$input->getOption('dry-run')) {
            $client->removeTorrents($obsoleteList);

            $names = $client->getTorrentsField($obsoleteList, Torrent\Get::NAME);
            $logger->info('Removed torrents:' . implode(', ', $names));
        } else {
            $output->writeln('dry-run, don\'t really remove');
        }
        $output->writeln('Found and deleted ' . count($obsoleteList) . ' obsolete torrents from transmission:');
        $client->printTorrentsTable($obsoleteList, $output);
        return 0;
    }
}
