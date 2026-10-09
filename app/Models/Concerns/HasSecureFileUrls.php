<?php

namespace App\Models\Concerns;

use App\Services\SecureFileService;

trait HasSecureFileUrls
{
    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();
        foreach (self::SECURE_FILE_FIELDS as $field) {
            $value = $this->getAttribute($field);
            if (is_array($value)) {
                $attributes[$field] = $this->withSecureUrls($value);
            } elseif (is_string($value)) {
                $attributes[$field . '_url'] = SecureFileService::url($value, $this);
            }
        }
        return $attributes;
    }

    private function withSecureUrls(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->withSecureUrls($value);
            } elseif (is_string($value) && in_array($key, ['file', 'file_path', 'attachment', 'attachment_path',
                'cnic_front', 'cnic_back', 'photo', 'picture', 'passport_attachment', 'other_attachment'], true)) {
                $values[$key . '_url'] = SecureFileService::url($value, $this);
            }
        }
        return $values;
    }
}
