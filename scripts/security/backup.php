<?php

// This utility is standalone: no Laravel bootstrap, .env load, database or network.
require __DIR__.'/lib/SecureBackup.php';

use Nccia\Security\SecureBackup;

set_error_handler(static function (): never {
    throw new RuntimeException('Protected filesystem operation failed.');
});

$options = getopt('', ['mode:', 'source:', 'archive:', 'target:', 'max-bytes:']);
$mode = $options['mode'] ?? 'plan';
if ($mode === 'plan') {
    echo json_encode(['status' => 'plan', 'operations' => ['create', 'verify', 'restore'],
        'required' => ['explicit --mode and --archive', 'NCCIA_BACKUP_KEY from protected environment', 'sodium PHP extension'],
        'create' => 'Supply --source with an offline consistent file snapshot. Archive must be new and outside source.',
        'restore' => 'Supply --target as a new directory under a private parent. Never restores into a database.',
        'limits' => 'Empty directories are not preserved. Default maximum restored data is 10 GiB. Configure OS ACLs separately.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit(0);
}
try {
    $key = SecureBackup::keyFromEnvironment();
    $archive = $options['archive'] ?? '';
    if ($archive === '') {
        throw new RuntimeException('Explicit archive path required.');
    }
    $max = filter_var($options['max-bytes'] ?? 10737418240, FILTER_VALIDATE_INT);
    if ($max === false || $max < 1) {
        throw new RuntimeException('Invalid data limit.');
    }
    $result = match ($mode) {
        'create' => SecureBackup::create($options['source'] ?? '', $archive, $key),
        'verify' => SecureBackup::verify($archive, $key, $max),
        'restore' => SecureBackup::restore($archive, $options['target'] ?? '', $key, $max),
        default => throw new RuntimeException('Unknown backup operation.'),
    };
    sodium_memzero($key);
    echo json_encode(['status' => 'passed', 'operation' => $mode] + $result, JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable) {
    // Avoid echoing private paths, file contents, supplied key or exception context.
    fwrite(STDERR, "Backup operation failed. Check arguments, permissions, key availability and archive integrity.\n");
    exit(1);
}
