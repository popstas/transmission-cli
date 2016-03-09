<?php

namespace Popstas\Transmission\Console\Command;

use Martial\Transmission\API\Argument\Torrent;
use Popstas\Transmission\Console\Helpers\TorrentListUtils;
use Popstas\Transmission\Console\Helpers\TorrentUtils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TorrentList extends Command
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('torrent-list')
            ->setAliases(['tl'])
            ->setDescription('List torrents')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Sort by torrent name (regexp)')
            ->addOption('age', null, InputOption::VALUE_OPTIONAL, 'Sort by torrent age, ex. \'>1 <5\'')
            ->addOption('sort', null, InputOption::VALUE_OPTIONAL, 'Sort by column number', 4)
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit torrent list')
            ->setHelp(<<<EOT
## List torrents

You can list torrents from transmission with `torrent-list` command:
```
transmission-cli torrent-list [--sort=column_number] [--name='name'] [--age='>0'] [--limit=10]
```

**List columns:**

- Name
- Id - need for `torrent-remove` command
- Age - days from torrent done date
- Size - size of downloaded data
- Uploaded - size of uploaded data
- Per day - average uploaded GB per day


#### Sorting list

You can sort torrents by `Per day` column and estimate unpopular torrents:
```
transmission-cli torrent-list --sort=6
```

For reverse sort ordering, add `-` to column number:
```
transmission-cli torrent-list --sort=-6
```


#### Filtering torrent list

**By age:**
```
transmission-cli torrent-list --age '>10'
transmission-cli torrent-list --age '< 20'
transmission-cli torrent-list --age '>0 <5'
```

**By name:**
You can use simple regular expression except `.` and `/` symbols.

Filter FullHD series:
```
transmission-cli torrent-list --name 'season*1080*'
```

Filter all mkv and avi:
```
transmission-cli torrent-list --name '.mkv|.avi'
```

#### Limiting torrent list

Output 10 worst torrents:
```
transmission-cli torrent-list --sort=6 --limit 10
```
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = $this->getApplication()->getClient();

        $torrentList = $client->getTorrentData();

        $torrentList = array_map(function ($torrent) {
            $torrent['age'] = TorrentUtils::getTorrentAgeInDays($torrent);
            return $torrent;
        }, $torrentList);

        $torrentList = TorrentListUtils::filterTorrents($torrentList, [
            'age' => $input->getOption('age'),
            'name' => $input->getOption('name'),
        ]);

        TorrentListUtils::printTorrentsTable(
            $torrentList,
            $output,
            $input->getOption('sort'),
            $input->getOption('limit')
        );

        $freeSpace = $client->getFreeSpace();
        $output->writeln('Free space: ' . TorrentUtils::getSizeInGb($freeSpace) . ' GB');
    }
}
