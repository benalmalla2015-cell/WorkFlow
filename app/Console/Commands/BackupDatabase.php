<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    protected $signature = 'workflow:backup-database';

    protected $description = 'Create a SQL backup of the current database on the configured backup disk';

    public function handle(): int
    {
        $connection = DB::connection();
        $database = $connection->getDatabaseName();
        $disk = config('workflow.backup_disk', 'local');
        $root = trim(config('workflow.backup_root', 'database_backups'), '/');
        $pdo = $connection->getPdo();

        $tables = collect($connection->select('SHOW TABLES'))->map(function ($row) {
            return array_values((array) $row)[0] ?? null;
        })->filter()->values();

        if ($tables->isEmpty()) {
            $this->error('No tables found for backup.');
            return self::FAILURE;
        }

        $sql = [];
        $sql[] = 'SET FOREIGN_KEY_CHECKS=0;';
        $sql[] = 'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";';
        $sql[] = 'START TRANSACTION;';
        $sql[] = 'USE `' . $database . '`;';
        $sql[] = '';

        foreach ($tables as $table) {
            $create = $connection->select('SHOW CREATE TABLE `' . $table . '`');
            $createStatement = $create[0]->{'Create Table'} ?? null;

            if (!$createStatement) {
                continue;
            }

            $sql[] = 'DROP TABLE IF EXISTS `' . $table . '`;';
            $sql[] = $createStatement . ';';

            $rows = $connection->table($table)->get()->map(fn ($row) => (array) $row);

            if ($rows->isNotEmpty()) {
                $columns = array_map(fn ($column) => '`' . str_replace('`', '``', $column) . '`', array_keys($rows->first()));
                $valuesSql = [];

                foreach ($rows as $row) {
                    $values = array_map(function ($value) use ($pdo) {
                        if ($value === null) {
                            return 'NULL';
                        }

                        if (is_bool($value)) {
                            return $value ? '1' : '0';
                        }

                        if (is_int($value) || is_float($value)) {
                            return (string) $value;
                        }

                        return $pdo->quote((string) $value);
                    }, array_values($row));

                    $valuesSql[] = '(' . implode(', ', $values) . ')';
                }

                $sql[] = 'INSERT INTO `' . $table . '` (' . implode(', ', $columns) . ') VALUES';
                $sql[] = implode(",\n", $valuesSql) . ';';
            }

            $sql[] = '';
        }

        $sql[] = 'COMMIT;';
        $sql[] = 'SET FOREIGN_KEY_CHECKS=1;';

        $filename = 'workflow_backup_' . now()->format('Ymd_His') . '.sql';
        $path = $root . '/' . $filename;

        Storage::disk($disk)->put($path, implode("\n", $sql));

        $this->info('Database backup created: ' . $path . ' on disk [' . $disk . ']');

        return self::SUCCESS;
    }
}
