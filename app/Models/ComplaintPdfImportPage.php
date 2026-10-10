<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One processed page of an OCR import; the (import, page_no) pair is the resume checkpoint. */
class ComplaintPdfImportPage extends Model
{
    protected $fillable = [
        'complaint_pdf_import_id', 'page_no', 'method', 'text', 'confidence', 'rotation', 'skew', 'engine', 'error', 'ms',
    ];

    protected $hidden = ['text'];

    protected function casts(): array
    {
        return ['confidence' => 'float', 'skew' => 'float'];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(ComplaintPdfImport::class, 'complaint_pdf_import_id');
    }
}
