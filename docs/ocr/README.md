# NCCIA OCR Import — setup, operation and deployment

Upload PDFs into a circle → Laravel queues them → the local Python engine extracts text page by page
(PyMuPDF for text layers, OpenCV + Tesseract for scans) → fields are extracted and validated → records
are created in that circle automatically **only** when every rule passes; everything else goes to the
review queue at **Complaints → OCR Import (Bulk)** (`/complaints/ocr-imports`).

## How it fits together

| Concern | Where |
|---|---|
| Upload, circle authorization, duplicate detection | `OcrImportController@store` → `OcrPipeline::register` |
| Page processing (chunked, resumable) | `ProcessOcrChunk` job → `python -m nccia_ocr pages FILE --start N --end M` |
| Per-page checkpoint | table `complaint_pdf_import_pages` (unique `import_id + page_no`) |
| Field extraction | `FinalizeOcrImport` job → `python -m nccia_ocr extract --pages-file …` (layout in `python/nccia_ocr/layouts/*.json`) |
| Final validation and the accept/review decision | `App\Services\Ocr\OcrFieldRules`, `OcrDecision` (Laravel is authoritative) |
| Writing records | `OcrRecordWriter` → existing `complaints` / `verification_reports` tables, inside the import's circle, one transaction |
| Review, corrections, audit | `PUT /api/ocr-imports/{id}/review`, logged in `activity_log` (`ocr_review`, `ocr_import`) |

Security properties:

* Python runs as a child process with an argument array (no shell, no network listener, no DB access).
* The circle is chosen at upload, checked with `User::canAccessCircle()`, stored on the import and read
  from the database by every job. OCR text never changes it; a different circle named in the document
  sends the import to review.
* Every API endpoint re-checks `ComplaintPdfImport::visibleTo()` + `canAccessCircle()`; other circles get 404.
* Duplicate detection (file SHA-256, and existing complaints by inquiry no / CNIC) only searches the same circle.
* PDFs are stored on the private disk under `storage/app/private/ocr/{circle_id}/…`, never under `public/`.
* Missing values are never filled with placeholders; the import waits for a reviewer instead.

## Local setup (Windows)

1. Install [Tesseract 5](https://github.com/UB-Mannheim/tesseract/wiki) (default path `C:\Program Files\Tesseract-OCR`).
2. Python 3.10+ virtual environment:
   ```powershell
   cd D:\NCCIA-main\python
   py -3.11 -m venv .venv
   .venv\Scripts\pip install -r requirements.txt
   .venv\Scripts\python -m nccia_ocr health
   ```
3. `.env`:
   ```
   OCR_PYTHON=D:\NCCIA-main\python\.venv\Scripts\python.exe
   OCR_TESSERACT_CMD=C:\Program Files\Tesseract-OCR\tesseract.exe
   QUEUE_CONNECTION=database
   ```
4. `php artisan migrate` then run a worker in a second terminal:
   ```powershell
   php artisan queue:work --queue=ocr,default --timeout=1000 --memory=512
   ```
5. `php artisan ocr:health` must report `OCR engine: OK`.

`QUEUE_CONNECTION=sync` works for a quick try but runs OCR inside the upload request — never use it for real volumes.

## Urdu / mixed documents

Tesseract ships only `eng` and `osd` here. For Urdu text in **scanned** pages install `urd.traineddata`
(tessdata_best recommended) into Tesseract's `tessdata` folder and set `OCR_LANGUAGES=eng+urd`; `ocr:health`
fails until the language file is present. Urdu in **text-layer** PDFs already works (Unicode NFKC
normalisation of Arabic presentation forms is applied). PaddleOCR is supported as an optional engine
(`OCR_ENGINE=paddle`) but is not installed; benchmark it before switching (see below).

## Benchmark before trusting a configuration

Put real, **manually verified** sample PDFs in a folder outside the repository, each with a
`<name>.truth.json` containing the correct values, e.g. `{"victim_cnic": "…", "victim_phone": "…"}`. Then:

```powershell
cd D:\NCCIA-main\python
.venv\Scripts\python -m nccia_ocr benchmark D:\ocr-benchmark --engines tesseract
.venv\Scripts\python -m nccia_ocr benchmark D:\ocr-benchmark --engines tesseract --languages eng+urd
```

The report gives CNIC/phone exact-match rates, per-field precision/recall, review rate and seconds per
page. Tune `OCR_MIN_FIELD_CONFIDENCE` / `OCR_AUTO_ACCEPT` from those numbers; keep `OCR_AUTO_ACCEPT=false`
(everything reviewed) until real-document results are acceptable. Measure handwritten documents as a
separate set — do not expect usable handwriting recognition from Tesseract.

## Which pages are OCR'd

Fields are read from the report section: it starts at the first page carrying the layout header (e.g. "VERIFICATION REPORT") and spans `primary_pages` in the layout file (1 for verification reports). Values found only in annexes are shown to the reviewer but never auto-accepted. Scanned pages after `OCR_MAX_OCR_PAGES` (default 5) are stored as *skipped* — they stay viewable in the original PDF. On a real 42-page case file this cut processing from ~40 minutes to ~3 minutes with identical field results. Set `OCR_MAX_OCR_PAGES=0` to OCR every page (needed only if annex text must be searchable).

## Throughput planning (≈1,000,000 PDFs)

Measured locally on synthetic documents (4-core laptop, Tesseract eng, 300 dpi, one worker): ~6 s per
**scanned** page including orientation detection; text-layer pages take milliseconds. Scanned volume
dominates: 1 M documents × 3 scanned pages ≈ 3 M pages ≈ 5,000 worker-hours. Run several workers (≈ one per CPU core, each Tesseract process is single-threaded) on a
dedicated OCR server; set `OMP_THREAD_LIMIT=1` for workers. Re-measure on the production hardware.

## Production deployment (Linux)

```bash
sudo apt install tesseract-ocr tesseract-ocr-eng   # + tesseract-ocr-urd for Urdu
cd /var/www/nccia/python && python3 -m venv .venv && .venv/bin/pip install -r requirements.txt
.venv/bin/python -m nccia_ocr health
```

`.env`: `OCR_PYTHON=/var/www/nccia/python/.venv/bin/python`, `QUEUE_CONNECTION=database` (or redis).

Supervisor (`/etc/supervisor/conf.d/nccia-ocr.conf`):

```ini
[program:nccia-ocr]
command=php /var/www/nccia/artisan queue:work database --queue=ocr --timeout=1000 --memory=768 --max-jobs=200
user=www-data
numprocs=4
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
stopwaitsecs=1100
environment=OMP_THREAD_LIMIT="1"
stdout_logfile=/var/log/nccia-ocr.log
```

Shared cPanel/CloudLinux hosting usually cannot run long-lived workers or install Tesseract; plan a VPS or a
separate OCR server that shares the database and storage.

Cron (recovery + monitoring):

```
*/10 * * * * php /var/www/nccia/artisan ocr:recover --minutes=30
*/5  * * * * php /var/www/nccia/artisan ocr:health --json > /var/www/nccia/storage/logs/ocr-health.json || echo "OCR unhealthy" | mail -s "NCCIA OCR" ops@example.invalid
```

## MySQL 8 verification (not yet done)

The automated tests run on SQLite. Before production, run them against a disposable MySQL 8 database
(never production): create `nccia_ocr_test`, point a copy of `phpunit.xml` at it, and run
`php artisan test --filter=OcrPipelineTest`. The migration uses only portable column types and a composite
unique index `(circle_id, file_hash)`; MySQL allows multiple NULLs in it, so legacy imports are unaffected.

## Storage and backup

* Uploaded PDFs: `storage/app/private/ocr/` — back up with the database; they are the evidence source.
* Page text: `complaint_pdf_import_pages.text` (LONGTEXT) — large; consider archiving rows of imported
  documents older than N months once the complaint is verified.
* Temporary files: `storage/app/private/ocr-tmp/` holds per-extraction JSONL files that are deleted in a
  `finally` block; anything left there after a crash is safe to delete.

## Recovery

* Worker crash / server restart: `php artisan ocr:recover` (also on cron). Finished pages are kept.
* A failed import: **Reprocess** in the UI, or `POST /api/ocr-imports/{id}/retry`.
* Rejected PDFs (encrypted, malformed, over the page/size limit) fail immediately without retries.

## Rollback

1. Stop OCR workers.
2. `php artisan migrate:rollback --step=1` removes `complaint_pdf_import_pages` and the new columns.
   Complaints already created by OCR remain (they are ordinary complaints with `source = pdf_import`).
3. Redeploy the previous release.

## Tests

```powershell
cd D:\NCCIA-main\python; python -m unittest discover -s tests -t .
cd D:\NCCIA-main; php -d extension=fileinfo -d extension=gd vendor/bin/phpunit tests/Feature/OcrPipelineTest.php
```

All test documents are synthetic (`python/tests/synthetic.py`). Never commit real PDFs, CNICs or phone numbers.
