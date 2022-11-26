<?php
declare(strict_types=1);

namespace TelkomselAggregatorTask;

use PDO;

/**
 * @mixin PDO
 */
class Sqlite
{
    public readonly Runner $runner;
    private ?PDO $connection = null;
    public readonly string $sqlite;

    public function __construct(Runner $runner)
    {
        $this->runner = $runner;
        $this->sqlite = ($_SERVER['HOME']??sys_get_temp_dir()).'/.sqlite/runner.db';
        if (!is_dir(dirname($this->sqlite))) {
            mkdir(dirname($this->sqlite), 0755, true);
        }
    }

    public function getConnection() : PDO
    {
        if (!$this->connection) {
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
    public function ping()
    {
        $this->getConnection()->query("SELECT 1");
        $this->checkMetaData();
    }

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
        $this->connection = null;
    }
}
