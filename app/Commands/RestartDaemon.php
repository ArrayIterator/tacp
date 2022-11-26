<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TelkomselAggregatorTask\Console;
use Throwable;

class RestartDaemon extends Command
{
    protected string $name = 'daemon:restart';
    protected string $description  = 'Restart the daemon';
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
        $daemon->checkData($input, $output, false, true);
        return self::SUCCESS;
    }

    private function checkTable(OutputInterface $output)
    {
        // ping the database
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
