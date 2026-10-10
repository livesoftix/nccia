<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Turns a caught exception into text that is safe to show in an API response.
 *
 * Messages the application deliberately raised for the user (plain
 * RuntimeException / InvalidArgumentException / DomainException, or an HTTP
 * abort) pass through unchanged. Anything else — database, filesystem, PDF or
 * framework errors — can reveal SQL, table names or server paths, so it is
 * logged with a reference and the client only receives that reference.
 */
class ClientError
{
    private const USER_FACING = [
        \RuntimeException::class,
        \InvalidArgumentException::class,
        \DomainException::class,
    ];

    public static function message(\Throwable $e): string
    {
        if ($e instanceof HttpExceptionInterface || in_array(get_class($e), self::USER_FACING, true)) {
            return $e->getMessage();
        }

        $reference = strtoupper(Str::random(8));
        Log::error('Unhandled exception returned to client', [
            'reference' => $reference,
            'exception' => $e,
        ]);

        return "An internal error occurred. Reference: {$reference}";
    }
}
