<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TelkomselAggregatorTask\Libraries\Console;
use TelkomselAggregatorTask\Runner;
use Throwable;

class CheckDaemon extends Command
{
    /**
     * @var string
     */
    protected string $name = 'daemon:check';

    /**
     * @var string
     */
    protected string $description  = 'Check the daemon';

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
        $this->setAliases(['check', 'check-daemon']);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
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
        $stopExists = is_string($this->console->runner->stop_file)
                      && file_exists($this->console->runner->stop_file);

        $php = $this->console->runner->php;
        $counted = count((array) $processes);
        $this->console->setAutoExit(true);
        $max = $this->console->runner->maxProcess;
        if ($counted === $max) {
            $output->writeln(
                sprintf(
                    "<fg=green>Daemon already running as expected: %d</>",
                    $max
                )
            );
            $output->writeln(sprintf("<fg=blue>Daemon running in:</> <fg=green>[%d] process</>", $max));
            foreach ($processes as $data) {
                $output->writeln(
                    sprintf(
                        '<fg=gray>status: [%s] pid: [%d] time: [%s] </> ',
                        $data['status'],
                        $data['pid'],
                        $data['time'],
                    )
                );
            }
            // checking table
            $this->checkTable($output);
            $output->writeln(
                '<fg=red>File ".stop" already exist! Application could not started! Please delete first.</>'
            );

            return self::SUCCESS;
        }

        $this->checkTable($output);

        if ($counted < $max) {
            if ($counted === 0) {
                $output->write("<fg=red>Daemon Stopped!</> ");
            } else {
                $output->writeln(
                    sprintf(
                        "<fg=red>Daemon running in [%d] process. Less than expected [%d]</>",
                        $counted,
                        $max
                    )
                );
            }
        } else {
            $output->writeln(
                sprintf(
                    "<fg=red>Daemon running in [%d] process. Greater than expected [%d]</>",
                    $counted,
                    $max
                )
            );
        }
        foreach ($processes as $data) {
            $output->writeln(
                sprintf(
                    '<fg=gray>status: [%s] pid: [%d] time: [%s] </> ',
                    $data['status'],
                    $data['pid'],
                    $data['time'],
                )
            );
        }
        if (!$stopExists) {
            $output->writeln(
                "<fg=blue>Please run:</>"
            );
            $file = $this->console->runner->file;
            $file = realpath($file) ?: $file;
            $output->writeln(
                "\t$php $file daemon:start"
            );
        } else {
            $output->writeln('');
            $output->writeln(
                "\tFile \".stop\" already exist! Application could not started! Please delete first."
            );
        }
        return self::SUCCESS;
    }

    /**
     * @param OutputInterface $output
     */
    private function checkTable(OutputInterface $output)
    {
        if (!$this->console->runner->postgre->tableExist(Runner::TABLE_CHECK)) {
            $output->writeln(
                sprintf(
                    '<fg=red>Table [%s] does not exists!</>',
                    Runner::TABLE_CHECK
                )
            );
            exit(255);
        }
        try {
            $this->console->runner->sqlite->ping();
        } catch (Throwable $e) {
            $output->writeln(
                '<fg=red>There was an error with sqlite!</>'
            );
            $output->writeln(
                sprintf('<fg=gray>%s</>', $e->getMessage())
            );
            exit(255);
        }
    }
}
