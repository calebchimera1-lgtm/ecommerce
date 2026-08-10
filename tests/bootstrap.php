<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap. Points the whole test run at a dedicated
 * `kymera_collection_test` database instead of the real dev database
 * (`kymera_collection`) - never run tests against real/dev data.
 *
 * The override works because App\Core\Env::load() only ever sets a
 * variable via putenv() if getenv() doesn't already return a value
 * (see app/Core/Env.php) - by putenv()'ing DB_DATABASE before
 * anything in the app loads .env, config/database.php's later
 * Env::load() call leaves our override alone and only fills in the
 * remaining values (host/username/password/etc.) from the real .env.
 *
 * If the test database doesn't exist yet, or exists but is empty,
 * this creates it and imports database/kymera_collection.sql - the
 * exact same schema fresh installs use - via the `mysql` CLI client
 * (chosen over executing the file through PDO because the schema file
 * is a plain sequential script relying on FOREIGN_KEY_CHECKS=0, not
 * something PDO's single-statement exec() is meant to run safely).
 */

putenv('DB_DATABASE=kymera_collection_test');
$_ENV['DB_DATABASE'] = 'kymera_collection_test';
$_SERVER['DB_DATABASE'] = 'kymera_collection_test';

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Env;

Env::load(dirname(__DIR__) . '/.env');

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', 3306);
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$database = (string) Env::get('DB_DATABASE', 'kymera_collection_test');

if (!str_ends_with($database, '_test')) {
    fwrite(STDERR, "Refusing to run tests against a database not ending in '_test' (got '{$database}'). Check tests/bootstrap.php.\n");
    exit(1);
}

$admin = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$admin->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$tableCount = (int) $admin
    ->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $admin->quote($database))
    ->fetchColumn();

if ($tableCount === 0) {
    // The schema file hardcodes `CREATE DATABASE IF NOT EXISTS
    // kymera_collection` / `USE kymera_collection` for fresh installs
    // - importing it as-is would silently create/seed the real dev
    // database regardless of which database name is passed on the
    // mysql CLI, since the file's own USE statement wins. Rewrite
    // both references to the test database name first.
    $schemaPath = dirname(__DIR__) . '/database/kymera_collection.sql';
    $schema = str_replace('`kymera_collection`', "`{$database}`", (string) file_get_contents($schemaPath));

    $tmpPath = tempnam(sys_get_temp_dir(), 'kymera_test_schema_');
    file_put_contents($tmpPath, $schema);

    $command = sprintf(
        'mysql --host=%s --port=%d --user=%s %s %s < %s',
        escapeshellarg($host),
        $port,
        escapeshellarg($username),
        $password !== '' ? escapeshellarg('--password=' . $password) : '',
        escapeshellarg($database),
        escapeshellarg($tmpPath)
    );

    exec($command . ' 2>&1', $output, $exitCode);
    unlink($tmpPath);

    if ($exitCode !== 0) {
        fwrite(STDERR, "Failed to seed test database '{$database}':\n" . implode("\n", $output) . "\n");
        exit(1);
    }
}
