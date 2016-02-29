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
The <info>download-series-add</info> add series to monitoring list.
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
