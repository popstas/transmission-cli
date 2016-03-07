<?php

namespace Popstas\Transmission\Console\Command;

use Martial\Transmission\API\Argument\Torrent;
use Popstas\Transmission\Console\Helpers\TorrentListUtils;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class TorrentRemove extends Command
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('torrent-remove')
            ->setAliases(['tr'])
            ->setDescription('Remove torrents')
            ->addArgument('torrent-ids', InputArgument::IS_ARRAY, 'List of torrent ids for remove')
            ->addOption('soft', null, InputOption::VALUE_NONE, 'Remove only from Transmission, not delete data')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Don\'t ask confirmation')
            ->setHelp(<<<EOT
## Remove torrents

Torrents removes only by id, you can see torrent id in `torrent-list` output.

By default torrents removes with data! Data deletes to trash on Mac OS and totally removes on Windows!

Remove one torrent:
```
transmission-cli torrent-remove 1
```

Remove several torrents:
```
transmission-cli torrent-remove 1 2
```

Remove only torrent from transmission, not delete data:
```
transmission-cli torrent-remove 1 --soft
```

For select not popular torrents and remove it, see `transmission-cli stats-get --help`
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = $this->getApplication()->getClient();
        $config = $this->getApplication()->getConfig();

        $torrentIds = $input->getArgument('torrent-ids');
        $torrentList = $client->getTorrentData($torrentIds);
        $notExistsIds = array_diff($torrentIds, TorrentListUtils::getArrayField($torrentList, Torrent\Get::ID));

        if (count($notExistsIds)) {
            foreach ($notExistsIds as $notExistsId) {
                $output->writeln($notExistsId . ' not exists');
            }
            return 1;
        }

        $output->writeln('Torrents for remove:');
        TorrentListUtils::printTorrentsTable($torrentList, $output);

        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Continue with this action? ', false);
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Aborting.');
                return 1;
            }
        }

        $this->dryRun($input, $output, function () use ($config, $torrentList, $client, $input, $output) {
            $influxDbClient = $this->getApplication()->getInfluxDbClient(
                $config->get('influxdb-host'),
                $config->get('influxdb-port'),
                $config->get('influxdb-user'),
                $config->get('influxdb-password'),
                $config->get('influxdb-database')
            );
            $transmissionHost = $config->get('transmission-host');
            $influxDbClient->sendTorrentPoints($torrentList, $transmissionHost);

            $deleteLocalData = !$input->getOption('soft');
            $client->removeTorrents($torrentList, $deleteLocalData);
            $output->writeln('Torrents removed.');
            if (!$deleteLocalData) {
                $output->writeln('Data don\'t removed.');
            }
        }, 'dry-run, don\'t really remove torrents');

        return 0;
    }
}
