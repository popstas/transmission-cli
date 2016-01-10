<?php

namespace Popstas\Transmission\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveDuplicatesCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('remove-duplicates')
            ->setDescription('Remove duplicates obsolete torrents')
            ->setDefinition(array(
                new InputOption('host', null, InputOption::VALUE_OPTIONAL, 'Transmission host'),
            ))
            ->setHelp(<<<EOT
The <info>remove-duplicates</info> removed all torrents with same name and smaller size than other.
EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = $this->getClient();

        $client->removeTorrents('all');

        $obsoleteList = $client->getObsoleteTorrents();
        if(!empty($obsoleteList)){
            $client->removeTorrents($obsoleteList);
            $output->writeln('Found and deleted '.count($obsoleteList).' obsolete torrents from transmission:');
            $client->printTorrentsTable($obsoleteList, $output);
        }
        else{
            $output->writeln('There are no obsolete torrents in Transmission.');
        }
    }
}