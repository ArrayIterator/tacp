<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask\Libraries\Database;

use JetBrains\PhpStorm\ArrayShape;
use PDO;
use TelkomselAggregatorTask\Runner;

/**
 * @mixin PDO
 */
class Sqlite extends AbstractDatabase
{
    public readonly Runner $runner;
    private ?PDO $connection = null;
    public readonly string $sqlite;

    private array $tables = [];

    public function __construct(Runner $runner)
    {
        parent::__construct($runner);
        $this->sqlite = ($_SERVER['HOME']??sys_get_temp_dir()).'/.sqlite/runner.db';
        if (!is_dir(dirname($this->sqlite))) {
            mkdir(dirname($this->sqlite), 0755, true);
        }
    }

    public function getConnection() : PDO
    {
        if (!$this->connection) {
            $this->runner->events->dispatch('on:before:createConnection', $this);

            if (!file_exists($this->sqlite)) {
                touch($this->sqlite);
            }

            $this->connection = new PDO(
                "sqlite:$this->sqlite",
                null,
                null,
                [
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT => true,
                    PDO::ATTR_EMULATE_PREPARES => true,
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]
            );

            $this->checkMetaData();
            $statement = $this->connection->query("
                        SELECT
                m.name as table_name,
                p.name as column_name,
                p.type as data_type,
                (CASE WHEN (p.`notnull` = 0) THEN 1 else 0 END) as is_nullable,
                p.dflt_value as column_default
            FROM sqlite_master as m
                LEFT OUTER JOIN pragma_table_info((m.name)) as p
            ON (m.name <> p.name)
            WHERE
                m.type = 'table' AND m.name != 'sqlite_sequence'
            ORDER BY
                m.name,
                p.cid
            ");
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                $maxLength = null;
                $lower = strtolower($row['table_name']);
                if (!isset($this->tables[$lower])) {
                    $this->tables[$lower] = [];
                }
                $dataType = $row['data_type'];
                if (str_contains($dataType, '(')) {
                    $match = null;
                    preg_match('~^([^(]+)(?:(\(.+)\))?$~', $dataType, $match);
                    if (!empty($match)) {
                        $dataType = $match[1];
                    }
                    if (!empty($match[2])) {
                        $match[2] = trim($match[2]);
                        $maxLength = is_numeric($match[2]) ? (int) $match[2] : null;
                    }
                }
                $this->tables[$lower]['name'] = $row['table_name'];
                $columnName = strtolower($row['column_name']);
                $this->tables[$lower]['columns'][$columnName] = [
                    'name' => $row['column_name'],
                    'data_type' => strtoupper($dataType),
                    'default' => $row['column_default'],
                    'max_length' => $maxLength,
                    'is_nullable' => (int) $row['is_nullable'] === 1,
                ];
            }

            $statement->closeCursor();

            $this->runner->events->dispatch('on:after:createConnection', $this);
        }

        return $this->connection;
    }

    public function checkMetaData()
    {
        if (!$this->connection) {
            return;
        }
        // enable multiple
        $table = 'task_queue';
        $this->connection->exec("
                PRAGMA journal_mode=wal;
                CREATE TABLE IF NOT EXISTS `$table`(
                   id INTEGER PRIMARY KEY AUTOINCREMENT,
                   task_id INTEGER NOT NULL UNIQUE,
                   status VARCHAR(255) NOT NULL DEFAULT 'pending',
                   result TEXT DEFAULT NULL,
                   created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                   updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
                CREATE TRIGGER IF NOT EXISTS task_queue_update_current_timestamp
                    AFTER UPDATE
                    ON `$table`
                BEGIN
                    UPDATE `$table`
                        SET updated_at=strftime('%Y-%m-%d %H:%M:%S', 'now'),
                        created_at=strftime('%Y-%m-%d %H:%M:%S', created_at)
                        WHERE (
                        id=NEW.id AND updated_at=NEW.updated_at
                    );
                END;
                CREATE TRIGGER IF NOT EXISTS task_queue_on_update_or_insert_datetime
                    AFTER INSERT
                        ON `$table`
                    BEGIN
                       UPDATE `$table` SET
                            created_at=strftime('%Y-%m-%d %H:%M:%S', created_at),
                            updated_at=strftime('%Y-%m-%d %H:%M:%S', new.updated_at)
                        WHERE id = NEW.id;
                    END;
            ");
    }

    public function ping() : bool
    {
        $result = parent::ping();
        if ($result) {
            $this->checkMetaData();
        }
        return $result;
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
}
