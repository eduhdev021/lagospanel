<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceAddon extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['price_minor' => 'integer', 'next_due' => 'immutable_date'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function addon()
    {
        return $this->belongsTo(ProductAddon::class, 'product_addon_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
