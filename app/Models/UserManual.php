<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserManual extends Model
{
    protected $fillable = ['title', 'audience', 'version', 'description'];
}
