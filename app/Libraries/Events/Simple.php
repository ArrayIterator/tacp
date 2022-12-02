<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries\Events;

use Closure;
use TelkomselAggregatorTask\Runner;

class Simple
{
    /**
     * @var array
     */
    private array $eventsList = [];

    /**
     * @var Runner
     */
    public readonly Runner $runner;

    /**
     * @param Runner $runner
     */
    public function __construct(Runner $runner)
    {
        $this->runner = $runner;
    }

    /**
     * @param string $eventName
     * @param Closure $callback
     * @param int $priority
     */
    public function add(string $eventName, Closure $callback, int $priority = 10)
    {
        $id = spl_object_hash($callback);
        $this->eventsList[$priority][$eventName][] = [$id, $callback];
        ksort($this->eventsList, SORT_ASC);
    }

    /**
     * @param string $name
     * @param ?Closure $callback
     * @param ?int $priority
     *
     * @return int
     */
    public function remove(string $name, ?Closure $callback = null, ?int $priority = null): int
    {
        $id = $callback ? spl_object_hash($callback) : null;
        $usePriority = $priority !== null;
        $removed = 0;
        foreach ($this->eventsList as $p => $eventList) {
            if ($usePriority && $p !== $priority) {
                continue;
            }
            foreach ($eventList as $eventName => $events) {
                if ($name !== $eventName) {
                    continue;
                }
                if ($id === null) {
                    $removed += count($this->eventsList[$p][$eventName]);
                    unset($this->eventsList[$p][$eventName]);
                    break;
                }
                foreach ($events as $k => $event) {
                    if ($event[0] !== $id) {
                        continue;
                    }
                    $removed++;
                    unset($this->eventsList[$p][$eventName][$k]);
                }
                if (count($this->eventsList[$p][$eventName]) === 0) {
                    unset($this->eventsList[$p][$eventName]);
                    continue;
                }
                $this->eventsList[$p][$eventName] = array_values(
                    $this->eventsList[$p][$eventName]
                );
            }

            if (count($this->eventsList[$p]) === 0) {
                unset($this->eventsList[$p]);
            }
        }

        return $removed;
    }

    /**
     * @param string $name
     * @param ?Closure $callback
     * @param ?int $priority
     *
     * @return bool
     */
    public function has(string $name, ?Closure $callback = null, ?int $priority = null): bool
    {
        $id = $callback ? spl_object_hash($callback) : null;
        $usePriority = $priority !== null;
        foreach ($this->eventsList as $p => $eventList) {
            if ($usePriority && $p !== $priority) {
                continue;
            }
            if (!isset($eventList[$name])) {
                continue;
            }
            if ($id === null) {
                return true;
            }
            foreach ($eventList[$name] as $event) {
                if ($event[0] === $id) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string $name
     * @param mixed|null $param
     * @param ...$args
     *
     * @return mixed
     */
    public function dispatch(string $name, mixed $param = null, ...$args): mixed
    {
        $originalParam = $param;
        foreach ($this->eventsList as $eventLists) {
            if (!isset($eventLists[$name])) {
                continue;
            }
            foreach ($eventLists[$name] as $event) {
                $param = $event[1]($param, $originalParam, $this->runner, ...$args);
            }
        }

        return $param;
    }

    /**
     * Clear All Events
     */
    public function clearAll()
    {
        $this->eventsList = [];
    }
}
