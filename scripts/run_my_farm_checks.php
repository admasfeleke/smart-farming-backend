<?php

declare(strict_types=1);

$target = [
    'DB_CONNECTION' => getenv('TEST_DB_CONNECTION') ?: 'mysql',
    'DB_HOST' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
    'DB_PORT' => getenv('TEST_DB_PORT') ?: '3306',
    'DB_DATABASE' => getenv('TEST_DB_DATABASE') ?: 'smart_farming_test',
    'DB_USERNAME' => getenv('TEST_DB_USERNAME') ?: 'root',
    'DB_PASSWORD' => getenv('TEST_DB_PASSWORD') ?: (getenv('DB_PASSWORD') ?: ''),
];

if ($target['DB_DATABASE'] === 'smart_farming') {
    fwrite(STDERR, "Refusing to run on production database name 'smart_farming'.\n");
    exit(1);
}

foreach ($target as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

$steps = [
    'php artisan config:clear --ansi',
    'php artisan migrate:fresh --seed --force --database=mysql',
    'php artisan test --filter=MyFarmApiTest',
];

foreach ($steps as $step) {
    echo ">> $step\n";
    passthru($step, $code);
    if ($code !== 0) {
        exit($code);
    }
}

echo "My Farm checks completed successfully.\n";
