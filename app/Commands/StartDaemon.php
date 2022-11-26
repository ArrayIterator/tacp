<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TelkomselAggregatorTask\Console;
use Throwable;

class StartDaemon extends Command
{
    protected string $name = 'daemon:start';
    protected string $description  = 'Start & Resolve daemon service';
    public readonly Console $console;

    /**
     * @param Console $console
     */
    public function __construct(Console $console)
    {
        $this->console = $console;
        parent::__construct($this->name);
        $this->setDescription($this->description);
        $this->setAliases(['daemon', 'start-daemon', 'resolve']);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->checkData($input, $output);
    }

    public function checkData(
        InputInterface $input,
        OutputInterface $output,
        $skip = true,
        $inRestart = false
    ) {
        $this->console->setAutoExit(!$skip);
        /**
         * @var SymfonyStyle $output
         */
        $processes = $this->console->runner->getProcess();
        if (is_array($processes) && ($skip || $inRestart)) {
            $processes = array_filter($processes, function ($e) {
                return !$e['current'];
            });
        }
        $counted = count((array) $processes);
        $nohup = $this->console->runner->nohup;
        $php = $this->console->runner->php;
        $cwd = $this->console->runner->cwd;
        $file = $this->console->runner->file;
        $maxProcess = $this->console->runner->maxProcess;
        chdir($cwd);
        $max = $maxProcess - $counted;
        // if process more than expected
        if ($counted > $maxProcess) {
            $theProcessPid = [];
            $output->writeln(sprintf(
                "<fg=yellow>Process greater than expected:</> %d in %d process",
                $maxProcess,
                $counted
            ));
            $current = $this->console->runner->pid;
            $kill = $this->console->runner->kill;
            foreach ($processes as $process) {
                if ($process['pid'] === $current) {
                    continue;
                }
                $counted--;
                if ($counted > $maxProcess) {
                    $theProcessPid[$process['pid']] = $process['pid'];
                }
            }

            if (!empty($theProcessPid)) {
                $process = implode(' ', $theProcessPid);
                $output->writeln(
                    sprintf("<fg=red>Killing PIDs:</> %s", $process)
                );
                $this->console->runner->shellArray(
                    "$kill -9 $process"
                );
            }
            $this->checkTable($output);
        } else {
            $this->checkTable($output);
            if ($maxProcess === $counted) {
                $output->writeln(
                    sprintf(
                        "<fg=green>Daemon already running as expected: %d</>",
                        $maxProcess
                    )
                );
            } elseif ($max > 0) {
                $command = "$nohup '$php' '$file' start > /dev/null 2>&1 &\n";
                if ($counted === 0) {
                    if (!$inRestart) {
                        $output->writeln("<fg=yellow>Daemon Stopped</>");
                    }
                } else {
                    $output->writeln(
                        sprintf(
                            "<fg=yellow>Process less than expected:</> %d, that should be %d processes",
                            $counted,
                            $this->console->runner->maxProcess
                        )
                    );
                }
                $output->write(sprintf('<fg=blue>Starting %d process:', $max));
                for (; $max > 0; $max--) {
                    $this->console->runner->shell($command);
                }
                $output->writeln(' <fg=green>Done</>');

                $processes = $this->console->runner->getProcess();
                if (is_array($processes) && ($skip || $inRestart)) {
                    $processes = array_filter($processes, function ($e) {
                        return !$e['current'];
                    });
                }

                $counted = count((array) $processes);
                $output->writeln(
                    sprintf("<fg=blue>Daemon running in :</> <fg=green>%d processes.</>", $counted)
                );
            }
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

        $output->writeln('');
        return self::SUCCESS;
    }

    private function checkTable(OutputInterface $output, $skip = false)
    {
        // ping the database
        if (!$this->console->runner->connection->tableExist('contents')) {
            $output->writeln(
                '<fg=red>Table [contents] does not exists!</>'
            );
            if ($skip) {
                exit(255);
            }
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
            if ($skip) {
                exit(255);
            }
        }
    }
}
