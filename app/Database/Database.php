<?php
declare(strict_types=1);

namespace App\Database;

use App\Config\AppConfig;
use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $driver = AppConfig::getDbDriver();

        try {
            if ($driver === 'mysql') {
                $host = AppConfig::get('DB_HOST', '127.0.0.1');
                $port = AppConfig::get('DB_PORT', '3306');
                $dbname = (string) AppConfig::get('DB_DATABASE', 'ppic');
                $user = AppConfig::get('DB_USERNAME', 'root');
                $pass = AppConfig::get('DB_PASSWORD', '');

                $pdoOptions = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
                try {
                    self::$pdo = new PDO($dsn, $user, $pass, $pdoOptions);
                } catch (PDOException $mysqlEx) {
                    $isUnknownDb = str_contains($mysqlEx->getMessage(), '1049')
                        || stripos($mysqlEx->getMessage(), 'Unknown database') !== false;

                    if ($isUnknownDb && preg_match('/^[a-zA-Z0-9_-]+$/', $dbname)) {
                        $serverPdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, $pdoOptions);
                        $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        self::$pdo = new PDO($dsn, $user, $pass, $pdoOptions);
                    } else {
                        throw $mysqlEx;
                    }
                }
            } else {
                // SQLite default
                $dbPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'ppic.sqlite';
                $dir = dirname($dbPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }

                self::$pdo = new PDO('sqlite:' . $dbPath, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                self::$pdo->exec('PRAGMA foreign_keys = ON;');
                self::$pdo->exec('PRAGMA journal_mode = WAL;');
            }
        } catch (PDOException $e) {
            throw new RuntimeException("Koneksi database gagal ({$driver}): " . $e->getMessage(), 0, $e);
        }

        return self::$pdo;
    }

    public static function select(string $sql, array $params = []): array
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function selectOne(string $sql, array $params = []): ?array
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $fields = implode(', ', $columns);
        $placeholders = implode(', ', array_map(fn($col) => ':' . $col, $columns));

        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($data);

        return (int) self::getConnection()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $fields = implode(', ', array_map(fn($col) => "{$col} = :set_{$col}", array_keys($data)));
        $sql = "UPDATE {$table} SET {$fields} WHERE {$where}";

        $bindings = [];
        foreach ($data as $key => $val) {
            $bindings['set_' . $key] = $val;
        }
        foreach ($whereParams as $key => $val) {
            $bindings[$key] = $val;
        }

        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    public static function delete(string $table, string $where, array $whereParams = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($whereParams);
        return $stmt->rowCount();
    }

    public static function statement(string $sql, array $bindings = []): int|bool
    {
        if (empty($bindings)) {
            return self::getConnection()->exec($sql);
        }
        $stmt = self::getConnection()->prepare($sql);
        return $stmt->execute($bindings);
    }

    public static function beginTransaction(): void
    {
        self::getConnection()->beginTransaction();
    }

    public static function commit(): void
    {
        self::getConnection()->commit();
    }

    public static function rollBack(): void
    {
        if (self::getConnection()->inTransaction()) {
            self::getConnection()->rollBack();
        }
    }
}
