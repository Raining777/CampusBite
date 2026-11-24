<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/accounts.php';

function run_sql_file(string $name): void
{
    $file = file_get_contents(dirname(__DIR__) . '/database/' . $name);
    foreach (preg_split('/;\s*(?:\R|$)/', $file) as $statement) {
        if (trim($statement) !== '') {
            db()->exec($statement);
        }
    }
}

try {
    if (count($argv) > 2 || (isset($argv[1]) && $argv[1] !== '--demo')) {
        throw new RuleViolation('Usage: php bin/install.php [--demo]');
    }
    run_sql_file('schema.sql');
    if (($argv[1] ?? '') === '--demo') {
        transaction(function () {
            if (row('SELECT id FROM users LIMIT 1') || row('SELECT id FROM vendors LIMIT 1')) {
                throw new RuleViolation('Demo data requires a fresh database. Existing records were preserved.');
            }
            run_sql_file('seed.sql');
        });
        echo "CampusBite demo database is ready. See docs/TESTER_GUIDE.md for accounts.\n";
        exit;
    }
    if (row("SELECT id FROM users WHERE role = 'admin' LIMIT 1")) {
        echo "CampusBite schema and initial administrator are ready.\n";
        exit;
    }
    $password = getenv('CAMPUSBITE_ADMIN_PASSWORD') ?: '';
    save_account([
        'username' => getenv('CAMPUSBITE_ADMIN_USERNAME') ?: '',
        'email' => getenv('CAMPUSBITE_ADMIN_EMAIL') ?: '',
        'password' => $password, 'confirm_password' => $password,
    ], 'admin');
    echo "CampusBite schema and initial administrator are ready.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Installation failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
