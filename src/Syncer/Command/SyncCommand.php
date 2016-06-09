<?php

namespace Syncer\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Syncer\SyncProvider;

class SyncCommand extends Command
{
    private $output;

    protected function configure()
    {
        $this
            ->setName('sync')
            ->setDescription('Start the sync between the two database')
            ->addOption(
                'config',
                null,
                InputOption::VALUE_OPTIONAL,
                'The path to the config file',
                './syncer_config.php'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $configPath = $input->getOption('config');
        $executePath = getcwd();

        if (!is_file($configPath)) {
            return $output->writeln("<error>Config file $configPath not found in the path [$executePath]</error>");
        }

        $config = @include_once $configPath;

        $provider = new SyncProvider($config);
        $managerActor = $provider->manager();

        if ($managerActor->count()) {
            foreach ($managerActor->actors() as $actor) {
                $output->writeln("<info>Actor \"{$actor->configurator()->name()}\"</info>");

                $output->writeln("<info>----</info>");
                $output->writeln("<info>Start Sync To -></info>");

                $actor->runSyncTo(function () {
                    call_user_func_array([$this, 'processLogger'], func_get_args());
                });
            }
        }

        $output->writeln("<info>\n----</info>");
        $output->writeln("<info>All sync completed</info>");
    }

    private function processLogger()
    {
        $event = func_get_arg(0) . ucfirst(func_get_arg(1));

        switch ($event) {
            case 'initialSyncBefore':
                $this->progress = new ProgressBar($this->output, count(func_get_arg(2)));
                $this->progress->start();
                break;
            case 'initialSyncProcess':
                $this->progress->advance();
                break;
            case 'initialSyncAfter':
                $this->progress->finish();
        }
    }
}
