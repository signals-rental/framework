<?php

namespace App\Services\ConnectionTesters;

use PDO;
use PDOException;

class PostgresConnectionTester
{
    /**
     * Test a PostgreSQL connection and return diagnostic info.
     *
     * @param  array{host: string, port: int, database: string, username: string, password: string}  $config
     * @return array{success: bool, version: string|null, error: string|null}
     */
    public function test(array $config): array
    {
        try {
            $pdo = $this->connect(
                $config['host'],
                $config['port'],
                $config['database'],
                $config['username'],
                $config['password'],
            );

            $version = $pdo->query('SELECT version()')->fetchColumn();
            preg_match('/PostgreSQL\s+([\d.]+)/', $version, $matches);

            return [
                'success' => true,
                'version' => $matches[0] ?? $version,
                'error' => null,
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'version' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test connection to the PostgreSQL server without specifying a target database.
     * Connects to the 'postgres' maintenance database to verify server reachability.
     *
     * @return array{success: bool, error: string|null}
     */
    public function testServer(string $host, int $port, string $username, string $password): array
    {
        try {
            $this->connect($host, $port, 'postgres', $username, $password);

            return ['success' => true, 'error' => null];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check if a database exists on the server.
     */
    public function databaseExists(string $host, int $port, string $username, string $password, string $database): bool
    {
        $pdo = $this->connect($host, $port, 'postgres', $username, $password);
        $stmt = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = :dbname');
        $stmt->execute(['dbname' => $database]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Create a database on the server.
     */
    public function createDatabase(string $host, int $port, string $username, string $password, string $database): void
    {
        $pdo = $this->connect($host, $port, 'postgres', $username, $password);
        $safeName = preg_replace('/[^a-zA-Z0-9_]/', '', $database);
        $pdo->exec("CREATE DATABASE \"{$safeName}\"");
    }

    /**
     * Check for required PostgreSQL extensions.
     *
     * @param  array{host: string, port: int, database: string, username: string, password: string}  $config
     * @param  string[]  $extensions
     * @return array<string, bool> Extension name => installed
     */
    public function checkExtensions(array $config, array $extensions): array
    {
        $pdo = $this->connect(
            $config['host'],
            $config['port'],
            $config['database'],
            $config['username'],
            $config['password'],
        );

        $results = [];
        foreach ($extensions as $ext) {
            $stmt = $pdo->prepare('SELECT 1 FROM pg_extension WHERE extname = :ext');
            $stmt->execute(['ext' => $ext]);
            $results[$ext] = (bool) $stmt->fetchColumn();
        }

        return $results;
    }

    protected function connect(string $host, int $port, string $database, string $username, string $password): PDO
    {
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $database);

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    }
}
