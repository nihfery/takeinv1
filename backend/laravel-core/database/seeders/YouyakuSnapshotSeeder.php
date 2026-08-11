<?php

namespace Database\Seeders;

use Illuminate\Database\Connection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class YouyakuSnapshotSeeder extends Seeder
{
    private const SNAPSHOT_FILE = 'data/youyaku.sql';

    /**
     * Restore the deterministic testing dataset captured in the SQL snapshot.
     *
     * The schema and migration history remain owned by Laravel migrations. Only
     * application table data is cleared and restored from INSERT statements.
     */
    public function run(): void
    {
        $snapshotPath = database_path('seeders/'.self::SNAPSHOT_FILE);

        if (! is_file($snapshotPath) || ! is_readable($snapshotPath)) {
            throw new RuntimeException("Database snapshot is missing or unreadable: {$snapshotPath}");
        }

        $snapshot = file_get_contents($snapshotPath);

        if ($snapshot === false || trim($snapshot) === '') {
            throw new RuntimeException("Database snapshot is missing or empty: {$snapshotPath}");
        }

        $connection = DB::connection();

        if ($connection->getDriverName() !== 'mysql') {
            throw new RuntimeException('YouyakuSnapshotSeeder requires a MySQL connection.');
        }

        $tables = array_values(array_filter(
            $this->extractTableNames($snapshot),
            fn (string $table): bool => $table !== 'migrations'
        ));
        $insertStatements = $this->extractInsertStatements($snapshot);

        if ($tables === [] || $insertStatements === []) {
            throw new RuntimeException('Database snapshot does not contain the expected table data.');
        }

        $missingTables = array_values(array_filter(
            $tables,
            fn (string $table): bool => ! $connection->getSchemaBuilder()->hasTable($table)
        ));

        if ($missingTables !== []) {
            throw new RuntimeException(
                'Run all migrations before importing the snapshot. Missing tables: '.implode(', ', $missingTables)
            );
        }

        $this->restoreSnapshot($connection, $tables, $insertStatements);

        $this->command?->info(sprintf(
            'YouYaku snapshot restored: %d tables cleared, %d INSERT statements imported.',
            count($tables),
            count($insertStatements)
        ));
    }

    /**
     * @return list<string>
     */
    private function extractTableNames(string $snapshot): array
    {
        preg_match_all('/^CREATE TABLE `([^`]+)`/m', $snapshot, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * @return list<string>
     */
    private function extractInsertStatements(string $snapshot): array
    {
        preg_match_all(
            '/^INSERT INTO `([^`]+)`/m',
            $snapshot,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        $statements = [];

        foreach ($matches[0] ?? [] as $index => $match) {
            $table = $matches[1][$index][0] ?? '';

            if ($table === 'migrations') {
                continue;
            }

            $statements[] = $this->extractStatementAt($snapshot, $match[1]);
        }

        return $statements;
    }

    private function extractStatementAt(string $snapshot, int $offset): string
    {
        $length = strlen($snapshot);
        $insideString = false;

        for ($position = $offset; $position < $length; $position++) {
            $character = $snapshot[$position];

            if ($insideString) {
                if ($character === '\\') {
                    $position++;

                    continue;
                }

                if ($character === "'") {
                    if (($snapshot[$position + 1] ?? null) === "'") {
                        $position++;

                        continue;
                    }

                    $insideString = false;
                }

                continue;
            }

            if ($character === "'") {
                $insideString = true;

                continue;
            }

            if ($character === ';') {
                return substr($snapshot, $offset, $position - $offset + 1);
            }
        }

        throw new RuntimeException("Unterminated INSERT statement at byte offset {$offset}.");
    }

    /**
     * @param  list<string>  $tables
     * @param  list<string>  $insertStatements
     */
    private function restoreSnapshot(Connection $connection, array $tables, array $insertStatements): void
    {
        $connection->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $connection->transaction(function () use ($connection, $tables, $insertStatements): void {
                foreach ($tables as $table) {
                    $connection->table($table)->delete();
                }

                foreach ($insertStatements as $statement) {
                    $connection->unprepared($statement);
                }
            });
        } finally {
            $connection->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
