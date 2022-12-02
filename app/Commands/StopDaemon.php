<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TelkomselAggregatorTask\Libraries\Console;

class StopDaemon extends Command
{
    protected string $name = 'daemon:stop';
    protected string $description  = 'Stop All daemon service';
    public readonly Console $console;

    /**
     * @param Console $console
     */
    public function __construct(Console $console)
    {
        $this->console = $console;
        parent::__construct($this->name);
        $this->setDescription($this->description);
        $this->setAliases(['stop', 'stop-daemon', 'kill']);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /**
         * @var SymfonyStyle $output
         */
        $processes = $this->console->runner->getProcess();
        $this->console->setAutoExit(true);
        $processes = array_filter($processes, function ($e) {
            return !$e['current'];
        });

        if (empty($processes)) {
            $output->writeln("<fg=green>No process to killed.</>");
            return self::FAILURE;
        }

        $kill = $this->console->runner->kill;
        $process = implode(' ', array_keys($processes));
        $this->console->runner->shellArray(
            "$kill -9 $process"
        );
        $oldProcess = $processes;
        $processes = $this->console->runner->getProcess();
        $processes = array_filter($processes, function ($e) {
            return !$e['current'];
        });
        $hung = [];
        foreach ($processes as $key => $data) {
            if (isset($oldProcess[$key])) {
                $hung[] = $key;
                unset($oldProcess[$key]);
            }
        }

        $output->writeln(
            sprintf("<fg=green>Stopped:</> <fg=gray>%s</>", implode(' ', array_keys($oldProcess)))
        );
        if (!empty($hung)) {
            $output->writeln(
                sprintf("<fg=red>Failed:</> <fg=gray>%s</>", implode(' ', $hung))
            );
        }
        return self::SUCCESS;
    }
}
