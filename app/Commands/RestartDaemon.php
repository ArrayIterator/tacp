<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TelkomselAggregatorTask\Libraries\Console;

class RestartDaemon extends Command
{
    /**
     * @var string
     */
    protected string $name = 'daemon:restart';

    /**
     * @var string
     */
    protected string $description  = 'Restart the daemon';

    /**
     * @var Console
     */
    public readonly Console $console;

    /**
     * @param Console $console
     */
    public function __construct(Console $console)
    {
        $this->console = $console;
        parent::__construct($this->name);
        $this->setDescription($this->description);
        $this->setAliases(['restart', 'restart-daemon']);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (is_string($this->console->runner->stop_file)
            && file_exists($this->console->runner->stop_file)
        ) {
            $output->writeln(
                '<fg=red>File ".stop" already exist! Application could not started! Please delete first.</>'
            );
            exit(0);
        }

        // ping
        /**
         * @var SymfonyStyle $output
         */
        $processes = $this->console->runner->getProcess();
        if (is_array($processes)) {
            $processes = array_filter($processes, function ($e) {
                return !$e['current'];
            });
        }

        $kill = $this->console->runner->kill;
        if (!empty($processes)) {
            $process = implode(' ', array_keys($processes));
            $output->writeln('<fg=blue>Stopping Daemon: </>' . $process);
            $this->console->runner->shellArray(
                "$kill -9 $process"
            );
        } else {
            $output->writeln('<fg=yellow>Daemon Stopped</>');
        }

        /**
         * @var StartDaemon $daemon
         */
        $daemon = $this->console->get('daemon:start');
        $daemon->checkData($output, false, true);
        return self::SUCCESS;
    }
}
