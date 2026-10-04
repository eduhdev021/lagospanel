<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateCommission extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function referredUser()
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
