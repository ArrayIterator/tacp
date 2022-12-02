<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries\Database;

use JetBrains\PhpStorm\ArrayShape;
use PDO;
use TelkomselAggregatorTask\Runner;

abstract class AbstractDatabase implements DatabaseInterface
{
    public readonly Runner $runner;

    public function __construct(Runner $runner)
    {
        $this->runner = $runner;
    }

    public function getRunner(): Runner
    {
        return $this->runner;
    }

    abstract public function getConnection(): PDO;

    #[ArrayShape([
        'name' => 'string',
        'columns' => 'array<string, array>'
    ])] abstract public function getTableDefinitions(string $tableName): ?array;

    public function tableExist(string $tableName) : bool
    {
        return $this->getTableDefinitions($tableName) !== null;
    }

    public function ping(): bool
    {
        $this->runner->events->dispatch('on:before:pingConnection', $this);
        $result = (bool) $this->getConnection()->exec('SELECT 1');
        $this->runner->events->dispatch('on:after:pingConnection', $this, $result);
        return $result;
    }

    abstract public function close();

    public function __call(string $name, array $arguments)
    {
        return call_user_func_array([$this->getConnection(), $name], $arguments);
    }

    public static function __callStatic(string $name, array $arguments)
    {
        return call_user_func_array([PDO::class, $name], $arguments);
    }

    public function __destruct()
    {
        $this->close();
    }
}