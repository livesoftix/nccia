<?php

namespace App\Services\Ocr;

use App\Models\Circle;
use Illuminate\Support\Carbon;

/**
 * Laravel-side validation of extracted (or reviewer-corrected) values. This is the
 * authoritative check: the Python engine's own validation is treated as advisory.
 */
class OcrFieldRules
{
    /** Field name => validation type. Unknown fields are rejected. */
    public const TYPES = [
        'tracking_no' => 'ref', 'inquiry_no' => 'ref', 'file_no' => 'ref',
        'victim_full_name' => 'name', 'victim_father_name' => 'name', 'victim_gender' => 'gender',
        'victim_cnic' => 'cnic', 'victim_phone' => 'phone', 'victim_email' => 'email',
        'victim_occupation' => 'text', 'victim_address' => 'text', 'victim_permanent_address' => 'text',
        'verification_date' => 'date', 'assignment_date' => 'date', 'report_date' => 'date',
        'crime_category' => 'text', 'crime_description' => 'longtext', 'city' => 'text',
        'amount_involved' => 'amount', 'recommendation' => 'text', 'reporting_officer' => 'text',
    ];

    /** @return array{0: bool, 1: mixed, 2: ?string} [valid, normalized value, issue] */
    public function check(string $field, mixed $value): array
    {
        $type = self::TYPES[$field] ?? null;
        if ($type === null) {
            return [false, null, 'unknown field'];
        }
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return [false, null, 'empty'];
        }
        $value = is_string($value) ? trim(preg_replace('/\s+/u', ' ', $value)) : $value;

        return match ($type) {
            'cnic' => preg_match('/^[1-7]\d{4}-\d{7}-\d$/', (string) $value)
                ? [true, $value, null] : [false, null, 'CNIC must match 12345-1234567-1'],
            'phone' => $this->phone((string) $value),
            'date' => $this->date((string) $value),
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? [true, strtolower($value), null] : [false, null, 'invalid email'],
            'amount' => is_numeric($value) && (float) $value >= 0 ? [true, (float) $value, null] : [false, null, 'invalid amount'],
            'ref' => preg_match('#^[A-Z0-9][A-Z0-9/\-]{2,60}$#i', (string) $value) ? [true, strtoupper($value), null] : [false, null, 'invalid reference'],
            'gender' => in_array(strtolower($value), ['male', 'female', 'other'], true) ? [true, ucfirst(strtolower($value)), null] : [false, null, 'invalid gender'],
            'name' => mb_strlen($value) >= 2 && mb_strlen($value) <= 120 && !preg_match('/\d/', $value)
                ? [true, $value, null] : [false, null, 'name must be 2-120 characters without digits'],
            'longtext' => mb_strlen($value) <= 10000 ? [true, $value, null] : [false, null, 'text too long'],
            default => mb_strlen($value) <= 255 ? [true, $value, null] : [false, null, 'text too long'],
        };
    }

    private function phone(string $value): array
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';
        if (str_starts_with($digits, '92') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '3')) {
            $digits = '0' . $digits;
        }

        return preg_match('/^0\d{9,10}$/', $digits) ? [true, $digits, null] : [false, null, 'invalid Pakistani phone number'];
    }

    private function date(string $value): array
    {
        try {
            $date = Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return [false, null, 'date must be YYYY-MM-DD'];
        }
        if (!$date || $date->format('Y-m-d') !== $value) {
            return [false, null, 'invalid date'];
        }
        if ($date->isFuture() || $date->year < 1950) {
            return [false, null, 'date out of range'];
        }

        return [true, $value, null];
    }

    /** Does a circle name/code found in the document refer to the upload circle? */
    public function circleMatches(?string $hint, Circle $circle): bool
    {
        if ($hint === null || trim($hint) === '') {
            return true;
        }
        $normalize = fn (?string $s) => preg_replace('/[^a-z]/', '', strtolower((string) $s));
        $hint = $normalize($hint);
        foreach ([$circle->name, $circle->code] as $candidate) {
            $c = $normalize($candidate);
            if ($c !== '' && (str_contains($hint, $c) || str_contains($c, $hint))) {
                return true;
            }
        }

        return false;
    }
}
