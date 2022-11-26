<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TelkomselAggregatorTask\Console;
use TelkomselAggregatorTask\TaskWorker\Worker;
use Throwable;

class StartApplication extends Command
{
    protected string $name = 'application:start';
    protected string $description  = 'Start the application';
    public readonly Console $console;
    private readonly int $wait;

    /**
     * @param Console $console
     */
    public function __construct(Console $console)
    {
        $this->console = $console;
        $this->wait = $console->runner->wait;
        parent::__construct($this->name);
        $this->setDescription($this->description);
        $this->setAliases(['start', 'start-application', 'up']);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $connection = $this->console->runner->connection;
        $this->checkTable($output);
        $this->console->setAutoExit(false);
        $connection->ping();
        $array = [true, false, true, false];

        /**
         * RUN FOREVER
         */
        try {
            // infinite loop
            while (true) {
                shuffle($array);
                if ($array[0] === true) {
                    try {
                        $args = $this->console->runner->argv;
                        if (!in_array('-q', $args)) {
                            array_push($args, '-q');
                        }
                        $theInput = new ArrayInput($args);
                        $output = new SymfonyStyle($theInput, new ConsoleOutput());
                        /**
                         * @var StartDaemon $daemon
                         */
                        $daemon = $this->console->get('daemon:start');
                        $daemon->checkData($theInput, $output, false);
                        unset($daemon);
                    } catch (Throwable $e) {
                        exit(255);
                    }
                }
                // processing
                $this->doProcess($input, $output);
            }
        } catch (Throwable) {
            return $this->execute($input, $output);
        }
    }

    protected function doProcess(InputInterface $input, OutputInterface $output)
    {
        try {
            $worker = new Worker($this->console->runner);
            $worker->start();
        } catch (Throwable $e) {
            // pass
        }
        // doing sleep
        sleep($this->wait);
    }

    private function checkTable(OutputInterface $output)
    {
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
