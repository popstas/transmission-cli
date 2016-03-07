<?php

namespace Popstas\Transmission\Console\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class Docs extends Command
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('_docs')
            ->setDescription('Generate docs for README.md')
            ->setHelp(<<<EOT
Developer command for generate docs in markdown. Typically usage:
```
bin/transmission-cli _docs > docs/commands.md
```
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $excludedCommands = ['_completion', '_docs', 'help', 'list'];

        /**
         * @var Command $command
         */
        $command = $this->getApplication()->find('list');
        $commandInput = new ArrayInput(['command' => 'list', '--raw' => true]);
        $commandOutput = new BufferedOutput();
        $command->run($commandInput, $commandOutput);
        $commandLines = explode("\n", $commandOutput->fetch());

        foreach ($commandLines as $commandLine) {
            $commandName = explode(' ', $commandLine)[0];
            if (empty($commandName) || in_array($commandName, $excludedCommands)) {
                continue;
            }
            $command = $this->getApplication()->find($commandName);
            $help = $command->getRawHelp();

            $output->writeln($help . "\n\n");
        }
    }
}
