<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['items' => 'array', 'total_minor' => 'integer', 'version' => 'integer', 'payment_days' => 'integer', 'valid_until' => 'immutable_date', 'sent_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function expired(): bool
    {
        return $this->valid_until->lt(today());
    }
}
