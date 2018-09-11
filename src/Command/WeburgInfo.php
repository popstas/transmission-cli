<?php

namespace Popstas\Transmission\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WeburgInfo extends Command
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('weburg-info')
            ->setAliases(['wi'])
            ->setDescription('Info about movie on weburg.net')
            ->addArgument('movie-id', InputArgument::REQUIRED, 'Movie ID or URL')
            ->setHelp(<<<EOT
## Info about movie on Weburg.net

Command for test internal weburg parser.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $this->getApplication()->getConfig();
        $config->set('weburg-request-delay', 0);
        $weburgClient = $this->getApplication()->getWeburgClient();
        $daysMax = $config->overrideConfig($input, 'days', 'weburg-series-max-age');
        $allowedMisses = $config->get('weburg-series-allowed-misses');

        try {
            $movieArgument = $input->getArgument('movie-id');
            $movieId = $weburgClient->cleanMovieId($movieArgument);
            if (!$movieId) {
                throw new \RuntimeException($movieId . ' seems not weburg movie ID or URL');
            }

            $movieInfo = $weburgClient->getMovieInfoById($movieId);
            foreach ($movieInfo as $name => $value) {
                if ($name == 'hashes') {
                    continue;
                }
                $output->writeln("$name: $value");
            }

            if (!empty($movieInfo['hashes'])) {
                $output->writeln('Search series...');
                if (!empty($movieInfo['hashes'])) {
                    $seriesUrls = $weburgClient->getSeriesTorrents(
                        $movieId,
                        $movieInfo['hashes'],
                        $daysMax,
                        $allowedMisses
                    );

                    if (count($seriesUrls)) {
                        $output->writeln('Found series: ' . count($seriesUrls));
                    }
                }
            }
        } catch (\RuntimeException $e) {
            $output->writeln($e->getMessage());
            return 1;
        }

        return 0;
    }
}
