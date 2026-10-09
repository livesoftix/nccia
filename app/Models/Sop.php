<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sop extends Model
{
    protected $fillable = ['title', 'department', 'version', 'effective_date', 'description'];
}
