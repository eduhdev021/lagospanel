<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceUpgrade extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['delta_minor' => 'integer'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function fromProduct()
    {
        return $this->belongsTo(Product::class, 'from_product_id');
    }

    public function toProduct()
    {
        return $this->belongsTo(Product::class, 'to_product_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
