<?php
declare(strict_types=1);

namespace Wisdom\Core;

use mysqli;
use mysqli_stmt;
use Throwable;

/**
 * Thin MySQLi wrapper. Every query goes through a prepared statement, so
 * user input is never concatenated into SQL.
 */
final class Database
{
    private mysqli $link;
    private int $transactionDepth = 0;

    /** @param array{host:string,user:string,pass:string,name:string,port:int,charset:string} $config */
    public function __construct(array $config)
    {
        // Make mysqli throw mysqli_sql_exception instead of emitting warnings.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $this->link = new mysqli($config['host'], $config['user'], $config['pass'], $config['name'], $config['port']);
        $this->link->set_charset($config['charset']);
        $this->link->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'");
    }

    /** Prepare, bind (types inferred) and execute a statement. */
    public function run(string $sql, array $params = []): mysqli_stmt
    {
        $statement = $this->link->prepare($sql);

        if ($params !== []) {
            $types = '';
            $values = [];
            foreach (array_values($params) as $value) {
                if (is_bool($value)) {
                    $value = (int) $value;
                }
                $types .= match (true) {
                    is_int($value)   => 'i',
                    is_float($value) => 'd',
                    default          => 's',
                };
                $values[] = $value;
            }
            $statement->bind_param($types, ...$values);
        }

        $statement->execute();

        return $statement;
    }

    /** @return list<array<string,mixed>> */
    public function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->run($sql, $params);
        $result = $statement->get_result();
        $rows = $result === false ? [] : $result->fetch_all(MYSQLI_ASSOC);
        $statement->close();

        return $rows;
    }

    /** @return array<string,mixed>|null */
    public function fetchRow(string $sql, array $params = []): ?array
    {
        $rows = $this->fetchAll($sql, $params);

        return $rows[0] ?? null;
    }

    public function fetchValue(string $sql, array $params = []): mixed
    {
        $row = $this->fetchRow($sql, $params);

        return $row === null ? null : reset($row);
    }

    /** Run INSERT/UPDATE/DELETE and return the affected-row count. */
    public function execute(string $sql, array $params = []): int
    {
        $statement = $this->run($sql, $params);
        $affected = $statement->affected_rows;
        $statement->close();

        return max(0, (int) $affected);
    }

    /** Run an INSERT and return the new auto-increment id. */
    public function insert(string $sql, array $params = []): int
    {
        $statement = $this->run($sql, $params);
        $id = (int) $this->link->insert_id;
        $statement->close();

        return $id;
    }

    /**
     * Run $callback atomically. Nested calls join the outer transaction.
     *
     * @template T
     * @param callable(self):T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        if ($this->transactionDepth > 0) {
            return $callback($this);
        }

        $this->link->begin_transaction();
        $this->transactionDepth++;
        try {
            $result = $callback($this);
            $this->link->commit();

            return $result;
        } catch (Throwable $exception) {
            $this->link->rollback();
            throw $exception;
        } finally {
            $this->transactionDepth--;
        }
    }

    /** Escape LIKE wildcards in user-supplied search text. */
    public static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
