<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'refunded_minor' => 'integer', 'provider_payload' => 'array', 'captured_at' => 'datetime'];
    }
}
