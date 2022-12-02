<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries\Database;

use JetBrains\PhpStorm\ArrayShape;
use PDO;
use TelkomselAggregatorTask\Runner;

interface DatabaseInterface
{
    /**
     * @param Runner $runner
     */
    public function __construct(Runner $runner);

    /**
     * @return Runner
     */
    public function getRunner() : Runner;

    /**
     * @return PDO
     */
    public function getConnection() : PDO;
    /**
     * @param string $tableName
     *
     * @return ?array
     */
    #[ArrayShape([
        'name' => 'string',
        'columns' => 'array<string, array>'
    ])] public function getTableDefinitions(string $tableName) : ?array;

    /**
     * @param string $tableName the table name
     *
     * @return bool
     */
    public function tableExist(string $tableName) : bool;

    /**
     * @return bool ping or reopen the connection
     */
    public function ping() : bool;

    /**
     * close the connections
     */
    public function close();
}
