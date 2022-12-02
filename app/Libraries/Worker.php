<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries;

use TelkomselAggregatorTask\Runner;

class Worker
{
    public readonly Runner $runner;

    public function __construct(Runner $runner)
    {
        $this->runner = $runner;
    }

    public function start()
    {
        // do process
        // @todo process select
    }
}
