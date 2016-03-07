<?php

namespace Popstas\Transmission\Console\Command;

use Martial\Transmission\API\Argument\Torrent;
use Popstas\Transmission\Console\TransmissionClient;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TorrentAdd extends Command
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('torrent-add')
            ->setAliases(['ta'])
            ->setDescription('Add torrents to Transmission')
            ->addArgument('torrent-files', InputArgument::IS_ARRAY, 'List of torrent files to add')
            ->setHelp(<<<EOT
## Add torrents

By default, Transmission may to freeze if you add several torrents at same time.
Therefore, preferred way to add torrents - with `torrent-add`.
After each add file command sleeps for 10 seconds for give time to freeze Transmission.
After that command waits for Transmission answer and add next file, etc.

```
transmission-cli torrent-add file|url [file2] [fileX]
```
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = $this->getApplication()->getClient();

        $torrentFiles = $input->getArgument('torrent-files');
        foreach ($torrentFiles as $torrentFile) {
            $this->addFile($input, $output, $client, $torrentFile);
            $output->writeln($torrentFile . ' added. Waiting for Transmission...');
            $client->waitForTransmission(10);
        }

        $output->writeln('All torrents added.');
    }

    private function addFile(InputInterface $input, OutputInterface $output, TransmissionClient $client, $torrentFile)
    {
        $this->dryRun($input, $output, function () use ($torrentFile, $client, $input, $output) {
            $client->addTorrent($torrentFile);
        }, 'dry-run, don\'t really add torrents');
    }
}
