<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value_ids' => 'array', 'quantity' => 'integer'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
