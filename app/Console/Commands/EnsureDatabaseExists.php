<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Throwable;

class EnsureDatabaseExists extends Command
{
    protected $signature = 'db:ensure {--connection= : The database connection to ensure exists}';

    protected $description = 'Create the configured database when it does not exist yet, without dropping anything';

    public function handle(): int
    {
        $name = $this->option('connection') ?: (string) config('database.default');
        $config = config("database.connections.{$name}");

        if (! is_array($config)) {
            $this->components->error("Unknown database connection [{$name}].");

            return self::FAILURE;
        }

        $database = $config['database'] ?? null;

        if (! is_string($database) || $database === '') {
            $this->components->error("Connection [{$name}] has no database name configured.");

            return self::FAILURE;
        }

        $driver = $config['driver'] ?? 'unknown';

        if ($driver !== 'pgsql') {
            $this->components->error("db:ensure supports PostgreSQL only; [{$name}] uses the [{$driver}] driver.");

            return self::FAILURE;
        }

        try {
            DB::connection($name)->getPdo();

            $this->components->info("Database [{$database}] already exists.");

            return self::SUCCESS;
        } catch (PDOException $e) {
            if (! $this->isUnknownDatabase($e)) {
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }
        }

        try {
            $pdo = $this->maintenanceConnection($config);
            $pdo->exec(sprintf('create database %s', $this->quoteIdentifier($database)));
        } catch (Throwable $e) {
            $this->components->error("Could not create [{$database}]: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->components->info("Created database [{$database}].");

        return self::SUCCESS;
    }

    /**
     * Quote a PostgreSQL identifier (not a string literal).
     */
    protected function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function maintenanceConnection(array $config): PDO
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 5432,
            config('database.maintenance_database'),
        );

        return new PDO($dsn, $config['username'] ?? null, $config['password'] ?? null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    protected function isUnknownDatabase(PDOException $e): bool
    {
        return $e->getCode() === '3D000' || str_contains($e->getMessage(), 'does not exist');
    }
}
