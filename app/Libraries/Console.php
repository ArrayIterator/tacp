<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries;

use Symfony\Component\Console\Application;
use TelkomselAggregatorTask\Commands\CheckDaemon;
use TelkomselAggregatorTask\Commands\RestartDaemon;
use TelkomselAggregatorTask\Commands\StartDaemon;
use TelkomselAggregatorTask\Commands\StartApplication;
use TelkomselAggregatorTask\Commands\StopDaemon;
use TelkomselAggregatorTask\Runner;

class Console extends Application
{
    public readonly Runner $runner;

    public function __construct(Runner $runner)
    {
        $this->runner = $runner;
        $this->setAutoExit(false);
        $this->addCommands([
            new RestartDaemon($this),
            new StartDaemon($this),
            new StartApplication($this),
            new StopDaemon($this),
            new CheckDaemon($this),
        ]);
        parent::__construct($this->runner->name, $this->runner->version);
    }

    public function start(): int
    {
        return $this->run($this->runner->input, $this->runner->console);
    }
}
