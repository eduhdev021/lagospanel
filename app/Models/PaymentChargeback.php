<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentChargeback extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'metadata' => 'array'];
    }
}
