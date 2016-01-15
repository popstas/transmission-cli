<?php

namespace Popstas\Transmission\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveDuplicatesCommand extends Command
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('remove-duplicates')
            ->setDescription('Remove duplicates obsolete torrents')
            ->setHelp(<<<EOT
The <info>remove-duplicates</info> removed all torrents with same name and smaller size than other.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->getLogger($output);
        $client = $this->getClient($output);

        $obsoleteList = $client->getObsoleteTorrents();
        if (!empty($obsoleteList)) {
            if (!$input->getOption('dry-run')) {
                $client->removeTorrents($obsoleteList);
            } else {
                $logger->info('dry-run, don\'t really remove');
            }
            $output->writeln('Found and deleted ' . count($obsoleteList) . ' obsolete torrents from transmission:');
            $client->printTorrentsTable($obsoleteList, $output);
        } else {
            $output->writeln('There are no obsolete torrents in Transmission.');
        }
    }
}
