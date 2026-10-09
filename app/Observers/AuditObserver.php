<?php

namespace App\Observers;

use App\Services\AuditLedgerService;
use Illuminate\Database\Eloquent\Model;

/**
 * Records create / update / delete of key records to the immutable audit ledger.
 */
class AuditObserver
{
    private const REDACT = ['password', 'remember_token', 'token', 'mfa_secret', 'mfa_recovery_codes'];

    public function created(Model $model): void
    {
        $this->log($model, 'created', $this->clean($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $changes = $this->clean($model->getChanges());
        unset($changes['updated_at']);
        if (empty($changes)) {
            return;
        }
        $this->log($model, 'updated', $changes);
    }

    public function deleted(Model $model): void
    {
        $this->log($model, 'deleted', ['id' => $model->getKey()]);
    }

    private function log(Model $model, string $action, array $properties): void
    {
        $name = strtolower(class_basename($model));
        AuditLedgerService::record("{$name}.{$action}", [
            'subject_type' => get_class($model),
            'subject_id'   => $model->getKey(),
            'properties'   => $properties,
        ]);
    }

    private function clean(array $attrs): array
    {
        foreach (self::REDACT as $k) {
            if (array_key_exists($k, $attrs)) {
                $attrs[$k] = '[redacted]';
            }
        }
        return $attrs;
    }
}
