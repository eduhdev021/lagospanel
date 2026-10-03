<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    public $timestamps = false;
}
