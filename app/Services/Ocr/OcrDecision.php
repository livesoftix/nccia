<?php

namespace App\Services\Ocr;

use App\Models\ComplaintPdfImport;

/**
 * Decides whether an extraction may be finalised automatically or must be reviewed.
 * OCR confidence is only one input; every value is re-validated by OcrFieldRules.
 */
class OcrDecision
{
    public function __construct(private OcrFieldRules $rules)
    {
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields  field_results from the engine (possibly reviewer-edited)
     * @return array{accept: bool, reasons: array<int, string>, values: array<string, mixed>}
     */
    public function evaluate(ComplaintPdfImport $import, array $fields, bool $reviewerConfirmed = false): array
    {
        $reasons = [];
        $values = [];
        $minConfidence = (float) config('ocr.min_field_confidence');

        foreach ($fields as $name => $field) {
            if (!array_key_exists($name, OcrFieldRules::TYPES)) {
                continue;
            }
            $value = $field['value'] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            [$ok, $normalized, $issue] = $this->rules->check($name, $value);
            if (!$ok) {
                $reasons[] = "{$name}: {$issue}";
                continue;
            }
            $values[$name] = $normalized;
            if ($reviewerConfirmed || !empty($field['reviewed'])) {
                continue;
            }
            foreach ($field['issues'] ?? [] as $engineIssue) {
                if (str_contains($engineIssue, 'conflict') || str_contains($engineIssue, 'corrected')) {
                    $reasons[] = "{$name}: {$engineIssue}";
                }
            }
            if (($field['valid'] ?? false) !== true) {
                $reasons[] = "{$name}: not confirmed valid by the extractor";
            } elseif ((float) ($field['confidence'] ?? 0) < $minConfidence) {
                $reasons[] = sprintf('%s: confidence %.2f below %.2f', $name, (float) ($field['confidence'] ?? 0), $minConfidence);
            }
        }

        foreach (config('ocr.required_fields') as $required) {
            if (!array_key_exists($required, $values)) {
                $reasons[] = "{$required}: required but missing or invalid";
            }
        }
        if (!collect(config('ocr.date_fields'))->contains(fn ($f) => array_key_exists($f, $values))) {
            $reasons[] = 'a report/verification date is required';
        }

        if (!$reviewerConfirmed && $import->circle && !$this->rules->circleMatches($import->circle_hint, $import->circle)) {
            $reasons[] = sprintf('document mentions circle "%s" but was uploaded to "%s"', $import->circle_hint, $import->circle->name);
        }
        if (!$reviewerConfirmed && $import->mean_confidence !== null && $import->mean_confidence < (float) config('ocr.min_page_confidence')) {
            $reasons[] = sprintf('mean page confidence %.2f below %.2f', $import->mean_confidence, (float) config('ocr.min_page_confidence'));
        }

        $reasons = array_values(array_unique($reasons));
        $accept = $reasons === [] && ($reviewerConfirmed || (bool) config('ocr.auto_accept'));

        return ['accept' => $accept, 'reasons' => $reasons, 'values' => $values];
    }
}
