<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OffenceType extends Model
{
    protected $fillable = ['group', 'name', 'value'];
}
