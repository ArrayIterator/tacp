<?php
use TelkomselAggregatorTask\Runner;

require __DIR__ . '/app/Runner.php';

$instance = Runner::instance();
// maximum process as daemon
$maxProcess = 5;
// sleep on before execute next loop
$sleepWait = 5;

$instance->start($maxProcess, $sleepWait);

__halt_compiler();
// stop here
