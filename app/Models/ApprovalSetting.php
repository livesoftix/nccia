<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ApprovalSetting extends Model
{
    protected $fillable = ['action_key', 'label', 'requirement', 'updated_by'];

    public const ACTIONS = [
        'arrest_warrant',
        'search_warrant',
        'raid_permission',
        'proclamation',
        'attachment',
    ];

    /**
     * Is Circle Incharge approval mandatory for this action?
     * Unknown / unset actions default to OPEN (not blocking), cached briefly.
     */
    public static function isMandatory(string $actionKey): bool
    {
        $map = Cache::remember('approval_settings_map', 300, function () {
            return static::query()->pluck('requirement', 'action_key')->all();
        });

        return ($map[$actionKey] ?? 'open') === 'mandatory';
    }

    public static function flushCache(): void
    {
        Cache::forget('approval_settings_map');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }
}
