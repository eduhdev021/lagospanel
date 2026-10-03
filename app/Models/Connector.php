<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Connector extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['settings' => 'array', 'active' => 'boolean', 'token' => 'encrypted'];
    }

    protected $hidden = ['token'];
}
