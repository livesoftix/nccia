<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    protected $fillable = ['name', 'code'];

    public function circles(): HasMany
    {
        return $this->hasMany(Circle::class);
    }
}
