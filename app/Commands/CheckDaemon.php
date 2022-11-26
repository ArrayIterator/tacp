<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TelkomselAggregatorTask\Console;
use Throwable;

class CheckDaemon extends Command
{
    protected string $name = 'daemon:check';
    protected string $description  = 'Check the daemon';
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

    protected function execute(InputInterface $input, OutputInterface $output)
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
        $output->writeln(
            "<fg=blue>Please run:</>"
        );
        $file = $this->console->runner->file;
        $file = realpath($file)?:$file;
        $output->writeln(
            "\t$php $file daemon:start"
        );
        return self::SUCCESS;
    }

    private function checkTable(OutputInterface $output)
    {
        // @todo ping
        if (!$this->console->runner->connection->tableExist('contents')) {
            $output->writeln(
                '<fg=red>Table [contents] does not exists!</>'
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
