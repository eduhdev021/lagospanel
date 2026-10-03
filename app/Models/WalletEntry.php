<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'balance_after_minor' => 'integer'];
    }
}
