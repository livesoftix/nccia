<?php

namespace App\Services\Ocr;

/** The PDF itself is unacceptable (malformed, encrypted, too large). Retrying will not help. */
class OcrRejected extends \RuntimeException
{
}
