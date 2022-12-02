<?php
use TelkomselAggregatorTask\Runner;

require __DIR__ . '/app/Runner.php';

$instance = Runner::instance();
// maximum process as daemon
$maxProcess = 5;
// sleep on before execute next loop
$sleepWait = 5;
// determine that loop process need to clear cycles
$collect_cycle_loop = 10;

$instance->start($maxProcess, $sleepWait, $collect_cycle_loop);

__halt_compiler();
// stop here
