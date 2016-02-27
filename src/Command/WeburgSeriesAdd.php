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

        $seriesArgument = $input->getArgument('series-id');
        $seriesArgument = str_replace('http://weburg.net/series/info/', '', $seriesArgument);
        if (!preg_match('/^\d+$/', $seriesArgument)) {
            $output->writeln($seriesArgument . ' seems not weburg series url');
            return 1;
        }

        $seriesList = $config->get('weburg-series-list');
        if (in_array($seriesArgument, $seriesList)) {
            $output->writeln($seriesArgument . ' already in list');
            return 0;
        }

        $seriesList[] = $seriesArgument;
        $config->set('weburg-series-list', $seriesList);
        $config->saveConfigFile();

        $output->writeln('Series ' . $seriesArgument . ' added to list');
        return 0;
    }
}
