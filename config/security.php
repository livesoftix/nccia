<?php

return [
    // Keep a dedicated audit key in secret storage across APP_KEY rotations.
    'audit_hmac_key' => env('AUDIT_HMAC_KEY'),
    'audit' => [
        'require_writes' => (bool) env('AUDIT_REQUIRE_WRITES', env('APP_ENV', 'production') === 'production'),
    ],
    'mfa' => [
        'issuer' => env('MFA_ISSUER', 'NCCIA'),
        'required_roles' => array_values(array_filter(array_map('trim', explode(',',
            (string) env('NCCIA_MFA_REQUIRED_ROLES', 'admin,superadmin,director_general,admin_forensic')
        )))),
        'require_all' => (bool) env('NCCIA_MFA_REQUIRE_ALL', false),
    ],
    'session_absolute_minutes' => (int) env('SESSION_ABSOLUTE_TIMEOUT', 480),
    'uploads' => [
        'max_file_bytes' => 50 * 1024 * 1024,
        'max_request_bytes' => 200 * 1024 * 1024,
        'max_files' => 100,
        'max_image_pixels' => 40000000,
        'scan_required' => (bool) env('UPLOAD_SCAN_REQUIRED', env('APP_ENV') === 'production'),
        'scanner_binary' => env('UPLOAD_SCANNER_BINARY', 'clamscan'),
        'scan_timeout_seconds' => (float) env('UPLOAD_SCAN_TIMEOUT', 10),
        'request_scan_seconds' => 60,
    ],
];
