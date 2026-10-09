<?php

return [
    'driver' => env('HASH_DRIVER', 'argon2id'),
    'bcrypt' => ['rounds' => env('BCRYPT_ROUNDS', 12), 'verify' => true, 'limit' => 72],
    // Migration compatibility: existing bcrypt hashes verify and are rehashed after
    // successful password proof. New secrets use Argon2id with no bcrypt truncation.
    'argon' => [
        'memory' => (int) env('ARGON_MEMORY', 65536),
        'threads' => (int) env('ARGON_THREADS', 1),
        'time' => (int) env('ARGON_TIME', 4),
        'verify' => false,
    ],
    'rehash_on_login' => true,
];
