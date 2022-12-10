<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries;

use PDO;
use TelkomselAggregatorTask\Libraries\Services\Cleaner;
use TelkomselAggregatorTask\Runner;
use Throwable;

class Worker
{
    public readonly Runner $runner;

    public function __construct(Runner $runner)
    {
        $this->runner = $runner;
    }

    public function start()
    {
    }

    /** @noinspection SqlResolve */
    public function doClean()
    {
        $this->runner->console->writeln(
            '<fg=blue>[i] Clean Check Every 6 Hours</>'
        );
        $timestamp = time();
        $six_hour = 3600*6;
        try {
            $lastClean = $this->runner->sqlite->query(
                "SELECT result FROM meta where task_name = 'cleaning'"
            )->fetch(PDO::FETCH_ASSOC);
            $exist = !empty($lastClean);
            $lastClean = $lastClean['result']??null;
            $lastClean = $lastClean ?: 0;
            $lastClean = is_numeric($lastClean) ? (int) $lastClean : $lastClean;
            $do = (!is_int($lastClean) || $lastClean && (
                ($lastClean + $six_hour) < $timestamp)
                || ($lastClean-3600) > $timestamp
            );
            if (!$do) {
                $this->runner->console->writeln(
                    '<fg=blue>[i] Skipped. Last clean is:</> '
                    . '('
                    . date('Y-m-d H:i:s T', $lastClean)
                    . ')'
                );
            }
        } catch (Throwable $e) {
            echo $e;
            return;
        }
        if (!$do) {
            return;
        }

        $query = !$exist ? "
            INSERT INTO meta (task_name, result) VALUES ('cleaning', '$timestamp')
        " : "UPDATE meta set result='$timestamp' WHERE task_name = 'cleaning'";
        $this->runner->sqlite->query($query);
        $this->runner->services->getService(Cleaner::class)?->process([]);
    }

    public function __destruct()
    {
        $this->doClean();
    }
}
