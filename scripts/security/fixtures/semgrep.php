<?php

// These are scanner fixtures; they are not loaded by the application or PHPUnit.
function literal_secret_defaults() {
    // ruleid: php-literal-secret-fallback
    $a = env('SERVICE_PASSWORD', 'public-fixture');
    // ruleid: php-literal-secret-fallback
    $b = env('SERVICE_TOKEN') ?? 'public-fixture';
    // ok: php-literal-secret-fallback
    $c = env('SERVICE_PASSWORD', '');
    // ok: php-literal-secret-fallback
    $d = env('SERVICE_PASSWORD');
}

function unsafe_eval($source) {
    // ruleid: php-no-dynamic-evaluation
    eval($source);
}

function unsafe_command($request) {
    $command = $request->input('command');
    // ruleid: php-request-to-command
    exec($command);
}

function safe_command($request) {
    // ok: php-request-to-command
    exec('tool '.escapeshellarg($request->input('argument')));
}

function unsafe_unserialize($request) {
    // ruleid: php-request-to-unserialize
    return unserialize($request->input('data'));
}

function unsafe_query($request, $query) {
    // ruleid: php-request-to-raw-sql
    return $query->whereRaw($request->input('filter'));
}

function safe_query($request, $query) {
    // ok: php-request-to-raw-sql
    return $query->whereRaw('name = ?', [$request->input('name')]);
}
