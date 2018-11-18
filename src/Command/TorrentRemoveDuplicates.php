<?php

namespace Popstas\Transmission\Console\Command;

use Martial\Transmission\API\Argument\Torrent;
use Popstas\Transmission\Console\Helpers\TorrentListUtils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class TorrentRemoveDuplicates extends Command
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('torrent-remove-duplicates')
            ->setAliases(['trd'])
            ->setDescription('Remove duplicates obsolete torrents')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Don\'t ask confirmation')
            ->setHelp(<<<EOT
## Remove series torrent duplicates

While download series every time you add torrent with same name to Transmission.
It corrupts `stats-send` command, therefore we need to remove old torrents. This command doing this.

Just call:
```
transmission-cli torrent-remove-duplicates
```

Command removes all torrents with same name and same download directory from Transmission.
**It not removes any files!**
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $this->getApplication()->getConfig();
        $client = $this->getApplication()->getClient();

        $torrentList = $client->getTorrentData();
        $obsoleteList = TorrentListUtils::getObsoleteTorrents($torrentList);
        if (empty($obsoleteList)) {
            $output->writeln('There are no obsolete torrents in Transmission.');
            return 0;
        }

        $output->writeln('Duplicate torrents for remove:');
        TorrentListUtils::printTorrentsTable($obsoleteList, $output);

        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Continue with this action? ', false);
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Aborting.');
                return 1;
            }
        }

        $this->dryRun($input, $output, function () use ($client, $obsoleteList, $config, $input, $output) {
            try {
                $influxDbClient = $this->getApplication()->getInfluxDbClient(
                    $config->get('influxdb-host'),
                    $config->get('influxdb-port'),
                    $config->get('influxdb-user'),
                    $config->get('influxdb-password'),
                    $config->get('influxdb-database')
                );
                $transmissionHost = $config->get('transmission-host');
                $influxDbClient->sendTorrentPoints($obsoleteList, $transmissionHost);
            } catch (\Exception $exception) {
                $output->writeln('InfluxDB not available');
            }

            $client->removeTorrents($obsoleteList);
            $names = TorrentListUtils::getArrayField($obsoleteList, Torrent\Get::NAME);
            $output->writeln('Removed torrents:' . implode(', ', $names));
        }, 'dry-run, don\'t really remove');

        $output->writeln('Found and deleted ' . count($obsoleteList) . ' obsolete torrents from transmission.');
        return 0;
    }
}
