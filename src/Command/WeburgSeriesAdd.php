<?php

namespace Popstas\Transmission\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WeburgSeriesAdd extends Command
{

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('weburg-series-add')
            ->setAliases(['wsa'])
            ->setDescription('Add series to monitoring list')
            ->addArgument('series-id', null, 'series id or series url')
            ->setHelp(<<<EOT
## Add series to download list

You can automatically download new series. To do this, you should add series to download list:
```
transmission-cli weburg-series-add http://weburg.net/series/info/12345
```

After that command `weburg-download` also will download series from list for last day.
If you don't want to download popular torrents, but only new series, use command:
```
transmission-cli weburg-download --series
```
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $this->getApplication()->getConfig();

        $weburgClient = $this->getApplication()->getWeburgClient();

        $seriesArgument = $input->getArgument('series-id');
        $seriesId = $weburgClient->cleanMovieId($seriesArgument);

        if (!$seriesId) {
            $output->writeln($seriesArgument . ' seems not weburg series url');
            return 1;
        }

        $seriesList = $config->get('weburg-series-list');
        if (in_array($seriesId, $seriesList)) {
            $output->writeln($seriesId . ' already in list');
            return 0;
        }

        $seriesList[] = $seriesId;
        $config->set('weburg-series-list', $seriesList);
        $config->saveConfigFile();

        $output->writeln('Series ' . $seriesId . ' added to list');
        return 0;
    }
}
