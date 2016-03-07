<?php

namespace Popstas\Transmission\Console\Command;

use Martial\Transmission\API\Argument\Torrent;
use Popstas\Transmission\Console\Helpers\TorrentListUtils;
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

        $this->dryRun($input, $output, function () use ($client, $obsoleteList, $config, $input, $output) {
            $influxDbClient = $this->getApplication()->getInfluxDbClient(
                $config->get('influxdb-host'),
                $config->get('influxdb-port'),
                $config->get('influxdb-user'),
                $config->get('influxdb-password'),
                $config->get('influxdb-database')
            );
            $transmissionHost = $config->get('transmission-host');
            $influxDbClient->sendTorrentPoints($obsoleteList, $transmissionHost);

            $client->removeTorrents($obsoleteList);
            $names = TorrentListUtils::getArrayField($obsoleteList, Torrent\Get::NAME);
            $output->writeln('Removed torrents:' . implode(', ', $names));
        }, 'dry-run, don\'t really remove');

        $output->writeln('Found and deleted ' . count($obsoleteList) . ' obsolete torrents from transmission:');
        TorrentListUtils::printTorrentsTable($obsoleteList, $output);
        return 0;
    }
}
