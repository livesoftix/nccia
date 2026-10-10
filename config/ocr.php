<?php

return [
    // Python interpreter that has the packages in python/requirements.txt (a venv path in production).
    'python' => env('OCR_PYTHON', 'python3'),
    'engine_dir' => base_path('python'),

    // Passed to the Python engine as environment variables.
    'engine' => env('OCR_ENGINE', 'tesseract'),
    'languages' => env('OCR_LANGUAGES', 'eng'),
    'tesseract_cmd' => env('OCR_TESSERACT_CMD'),
    'dpi' => (int) env('OCR_DPI', 300),
    'max_pages' => (int) env('OCR_MAX_PAGES', 1000),
    'max_file_mb' => (int) env('OCR_MAX_FILE_MB', 512),
    // Scanned pages after this are stored as "skipped" (fields come from the leading report
    // pages; annexes remain viewable in the PDF). 0 = OCR every page.
    'max_ocr_pages' => (int) env('OCR_MAX_OCR_PAGES', 5),

    // Pages per queue job, and the time one job may spend on them.
    'chunk_pages' => (int) env('OCR_CHUNK_PAGES', 10),
    'chunk_timeout' => (int) env('OCR_CHUNK_TIMEOUT', 900),
    'queue' => env('OCR_QUEUE', 'ocr'),
    'disk' => env('OCR_DISK', 'local'),

    // Automatic finalisation rules. Anything not satisfying them goes to the review queue.
    'auto_accept' => env('OCR_AUTO_ACCEPT', true),
    'min_field_confidence' => (float) env('OCR_MIN_FIELD_CONFIDENCE', 0.85),
    'min_page_confidence' => (float) env('OCR_MIN_PAGE_CONFIDENCE', 0.70),

    // Fields the complaints table needs; each must be extracted and valid (or supplied by a reviewer).
    'required_fields' => [
        'victim_full_name', 'victim_cnic', 'victim_phone', 'victim_address', 'crime_category',
    ],
    // One of these must be present for report/occurrence dates.
    'date_fields' => ['verification_date', 'report_date', 'assignment_date'],
];
