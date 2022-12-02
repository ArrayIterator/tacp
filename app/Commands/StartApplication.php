<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TelkomselAggregatorTask\Libraries\Console;
use TelkomselAggregatorTask\Libraries\Worker;
use TelkomselAggregatorTask\Runner;
use Throwable;

class StartApplication extends Command
{
    /**
     * @var string
     */
    protected string $name = 'application:start';

    /**
     * @var string
     */
    protected string $description  = 'Start the application';

    /**
     * @var Console
     */
    public readonly Console $console;

    /**
     * @var int
     */
    private readonly int $wait;

    /**
     * @var int
     */
    private readonly int $loopInCheck;

    /**
     * @param Console $console
     */
    public function __construct(Console $console)
    {
        $this->console = $console;
        $this->wait = $console->runner->wait;
        $this->loopInCheck = 3;
        parent::__construct($this->name);
        $this->setDescription($this->description);
        $this->setAliases(['start', 'start-application', 'up']);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (is_string($this->console->runner->stop_file)
            && file_exists($this->console->runner->stop_file)
        ) {
            $output->writeln(
                '<fg=red>File ".stop" already exist! Application could not started! Please delete first.</>'
            );
            exit(0);
        }

        $count = $this->console->runner->collect_cycle_loop;
        $this->console->setAutoExit(false);

        $this->checkTable($output);

        // array shuffling to recheck
        $countDaemon = $this->loopInCheck;

        /**
         * RUN FOREVER
         */
        try {
            // infinite loop
            while (true) {
                /**
                 * If file .stop
                 */
                if (is_string($this->console->runner->stop_file)
                    && file_exists($this->console->runner->stop_file)
                ) {
                    gc_collect_cycles();

                    exit(0);
                }

                // read the config
                if ($count < 1) {
                    $count = $this->console->runner->collect_cycle_loop;
                    $this->console->runner->collectCycles();
                }

                $countDaemon--;
                if (($countDaemon <= 0)) {
                    $countDaemon = $this->loopInCheck;
                    try {
                        $args = $this->console->runner->argv;
                        if (!in_array('-q', $args)) {
                            array_push($args, '-q');
                        }
                        $theInput = new ArrayInput($args);
                        $output = $this->console->runner->createNewConsoleOutput($theInput);
                        /**
                         * @var StartDaemon $daemon
                         */
                        $daemon = $this->console->get('daemon:start');
                        $daemon->checkData($output, false);
                        unset($daemon);
                    } catch (Throwable $e) {
                        exit(255);
                    }
                }
                $count--;
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
        } catch (Throwable) {
            // pass
        }
        unset($worker);
        // doing sleep
        sleep($this->wait);
    }

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
