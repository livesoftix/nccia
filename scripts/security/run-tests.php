<?php

// PHPUnit must never inherit a developer's MySQL connection or cached production config.
$root = dirname(__DIR__, 2);
chdir($root);

if (is_file($root.'/bootstrap/cache/config.php')) {
    fwrite(STDERR, "Refusing tests with cached application config. Run php artisan config:clear first.\n");
    exit(1);
}

$environment = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'APP_URL' => 'http://localhost',
    'APP_CONFIG_CACHE' => $root.'/bootstrap/cache/security-testing-config.php',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'DATABASE_URL' => '',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'MAIL_MAILER' => 'array',
    'BROADCAST_CONNECTION' => 'null',
];

if (is_file($environment['APP_CONFIG_CACHE'])) {
    fwrite(STDERR, "Refusing tests with cached testing config. Remove bootstrap/cache/security-testing-config.php.\n");
    exit(1);
}

foreach ($environment as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

require $root.'/vendor/autoload.php';

exit((new PHPUnit\TextUI\Application)->run(array_merge(
    ['phpunit', '--configuration', $root.'/phpunit.xml'],
    array_slice($argv, 1),
)));
