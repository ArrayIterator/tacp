<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries\Database;

use JetBrains\PhpStorm\ArrayShape;
use PDO;
use Throwable;

/**
 * @mixin PDO
 */
class Postgre extends AbstractDatabase
{
    private array $databaseConfig = [];

    /**
     * @var PDO
     */
    private ?PDO $connection = null;

    /**
     * @var bool
     */
    private bool $checked = false;

    /**
     * @var array<string, array<string, array|string>>
     */
    #[ArrayShape([
        'string' => [
            'name' => 'string',
            'columns' => 'array<string, array>'
        ]
    ])] private array $tables = [];

    /**
     * Check Configurations
     */
    public function check()
    {
        if ($this->checked) {
            return;
        }

        $this->checked = true;
        try {
            if (!$this->runner->configFile) {
                $this->runner->printError(
                    "\033[0;31mConfiguration file not found\033[0m\n"
                );
                exit(255);
            }
            $config = $this->runner->config;
            if (empty($config)) {
                $this->runner->printError(
                    "\033[0;31mConfiguration is invalid\033[0m\n"
                );
                exit(255);
            }
            if (!isset($config['database']) || !is_array($config['database'])) {
                $this->runner->printError(
                    "\033[0;31mConfiguration database is invalid \033[0m\n"
                );
                exit(255);
            }
            $mustBeExists = [
                'dbname',
                'dbuser',
                'dbpassword',
            ];
            foreach ($mustBeExists as $key) {
                if (isset($config['database'][$key]) && is_string($config['database'][$key])) {
                    unset($mustBeExists);
                }
            }
            if (!empty($mustBeExists)) {
                $this->runner->printError(
                    [
                        "\033[0;31mConfiguration file is invalid \033[0m\n",
                        sprintf(
                            "%s has not found!",
                            implode(', ', $mustBeExists)
                        )
                    ]
                );
                exit(255);
            }
        } catch (Throwable $e) {
            $this->runner->printError(
                [
                    "\033[0;31mThere was an error : \033[0m\n",
                    $e->getMessage()
                ]
            );
            exit(255);
        }
        $this->databaseConfig = $config['database'];
        try {
            $this->connection = $this->getConnection();
        } catch (Throwable $e) {
            $this->runner->printError(
                [
                    "\033[0;31mThere was an error : \033[0m\n",
                    $e->getMessage()
                ]
            );
            exit(255);
        }
    }

    /**
     * @return PDO
     */
    public function getConnection() : PDO
    {
        if (!$this->connection) {
            $this->runner->events->dispatch('on:before:createConnection', $this);

            $this->check();
            $host = $this->databaseConfig['dbhost']??'localhost';
            $port = $this->databaseConfig['dbport']??null;
            $dbName = $this->databaseConfig['dbname'];
            $user = $this->databaseConfig['dbuser'];
            $password = $this->databaseConfig['dbpassword'];
            $dsn = "pgsql:host={$host};dbname=$dbName;"
                   . (is_int($port) ? "port={$port};": '');
            $this->connection = new PDO(
                $dsn,
                $user,
                $password,
                [
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT => true,
                    PDO::ATTR_EMULATE_PREPARES => true,
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]
            );
            $this->connection->query("SET NAMES 'UTF8'");
            $table = $this->connection->quote($dbName);
            $statement = $this->connection->query("
                SELECT
                   column_name,
                   table_name,
                   column_default,
                   data_type,
                   (CASE WHEN (UPPER(is_nullable) = 'YES') THEN 1 else 0 END) as is_nullable,
                   character_maximum_length as max_length
                FROM 
                     information_schema.columns
                WHERE
                    table_catalog = {$table}
                    AND table_schema = 'public'
                ORDER BY table_name, ordinal_position
            ");
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                $lower = strtolower($row['table_name']);
                if (!isset($this->tables[$lower])) {
                    $this->tables[$lower] = [];
                }
                $this->tables[$lower]['name'] = $row['table_name'];
                $columnName = strtolower($row['column_name']);
                $this->tables[$lower]['columns'][$columnName] = [
                    'name' => $row['column_name'],
                    'data_type' => strtoupper($row['data_type']),
                    'default' => $row['column_default'],
                    'max_length' => $row['max_length'] ? (int) $row['max_length'] : null,
                    'is_nullable' => (int) $row['is_nullable'] === 1,
                ];
            }

            $statement->closeCursor();

            $this->runner->events->dispatch('on:after:createConnection', $this);
        }

        return $this->connection;
    }

    /**
     * @param string $tableName
     *
     * @return ?array
     */
    #[ArrayShape([
        'name' => 'string',
        'columns' => 'array<string, array>'
    ])] public function getTableDefinitions(string $tableName) : ?array
    {
        // check connection
        $this->getConnection();
        return $this->tables[strtolower(trim($tableName))]??null;
    }

    /**
     * @inheritdoc
     */
    public function close()
    {
        $this->runner->events->dispatch('on:before:closeConnection', $this);
        $this->connection = null;
        $this->tables = [];
        $this->runner->events->dispatch('on:after:closeConnection', $this);
    }
}
