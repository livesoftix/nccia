<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Project password policy: length and offline common/context blocklist, no composition rules. */
class StrongPassword implements ValidationRule
{
    public static function rules(bool $required = true, bool $confirmed = false): array
    {
        return array_merge([$required ? 'required' : 'nullable', 'string', 'min:15', 'max:128', new self],
            $confirmed ? ['confirmed'] : []);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            return;
        }
        $catalogue = json_decode(file_get_contents(resource_path('security/common-passwords-v1.json')), true, 512, JSON_THROW_ON_ERROR);
        if (in_array(hash('sha256', mb_strtolower($value)), $catalogue['digests'], true)) {
            $fail('Choose a password that is not a common or organization-specific phrase.');
        }
    }
}
