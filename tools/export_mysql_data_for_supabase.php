<?php
declare(strict_types=1);

// Export the current XAMPP database as a one-time PostgreSQL data import.
// Run from the project root with: php tools/export_mysql_data_for_supabase.php
$host = getenv('SOURCE_MYSQL_HOST') ?: '127.0.0.1';
$port = getenv('SOURCE_MYSQL_PORT') ?: '3306';
$database = getenv('SOURCE_MYSQL_DATABASE') ?: 'kopi_cf';
$username = getenv('SOURCE_MYSQL_USER') ?: 'root';
$password = getenv('SOURCE_MYSQL_PASSWORD') ?: '';

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$tables = [
    'users' => ['id', 'name', 'email', 'password_hash', 'role', 'created_at'],
    'symptoms' => ['id', 'code', 'name', 'description', 'category', 'active'],
    'diseases' => ['id', 'code', 'name', 'scientific_name', 'description', 'advice', 'active'],
    'rules' => ['id', 'disease_id', 'symptom_id', 'cf_expert', 'note'],
    'diagnoses' => ['id', 'user_id', 'disease_name', 'confidence', 'results_json', 'answers_json', 'general_json', 'created_at'],
];

$sql = [
    '-- Private one-time data transfer from the local KopiCare MySQL database.',
    '-- Run after supabase.sql and before the first production login.',
    'BEGIN;',
];

foreach ($tables as $table => $columns) {
    $postgresColumns = implode(', ', array_map(static fn(string $column): string => '"'.$column.'"', $columns));
    $mysqlColumns = implode(', ', array_map(static fn(string $column): string => '`'.$column.'`', $columns));
    $updateList = implode(', ', array_map(static fn(string $column): string => '"'.$column.'" = EXCLUDED."'.$column.'"', array_filter($columns, static fn(string $column): bool => $column !== 'id')));
    $statement = $pdo->query('SELECT '.$mysqlColumns.' FROM `'.$table.'` ORDER BY id');

    foreach ($statement as $row) {
        $values = [];
        foreach ($columns as $column) {
            $value = $row[$column];
            if ($value === null) {
                $values[] = 'NULL';
            } else {
                if (str_contains((string)$value, "\0")) {
                    throw new RuntimeException("NUL byte cannot be stored in PostgreSQL text (table {$table}).");
                }
                $values[] = "'".str_replace("'", "''", (string)$value)."'";
            }
        }

        $sql[] = 'INSERT INTO public."'.$table.'" ('.$postgresColumns.') VALUES ('.implode(', ', $values).') ON CONFLICT (id) DO UPDATE SET '.$updateList.';';
    }
}

foreach (array_keys($tables) as $table) {
    $sql[] = "SELECT setval(pg_get_serial_sequence('public.{$table}', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM public.\"{$table}\"), 1), 1), EXISTS (SELECT 1 FROM public.\"{$table}\"));";
}
$sql[] = 'COMMIT;';

$directory = dirname(__DIR__).'/.private';
if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
    throw new RuntimeException('Could not create the private export directory.');
}
$path = $directory.'/supabase-data-import.sql';
if (file_put_contents($path, implode("\n", $sql)."\n", LOCK_EX) === false) {
    throw new RuntimeException('Could not write the private PostgreSQL import file.');
}
@chmod($path, 0600);
fwrite(STDOUT, "Data export created at {$path}\n");
foreach (array_keys($tables) as $table) {
    $count = (int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
    fwrite(STDOUT, "{$table}: {$count} rows\n");
}
