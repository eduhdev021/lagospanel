<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Affiliate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rate_percent' => 'integer',
            'clicks' => 'integer',
            'available_minor' => 'integer',
            'total_earned_minor' => 'integer',
            'total_withdrawn_minor' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function commissions()
    {
        return $this->hasMany(AffiliateCommission::class);
    }
}
