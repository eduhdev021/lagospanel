<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['provisioning' => 'array', 'allow_quantity' => 'boolean', 'max_per_user' => 'integer', 'active' => 'boolean', 'price_minor' => 'integer', 'setup_minor' => 'integer', 'stock' => 'integer'];
    }

    public function options()
    {
        return $this->hasMany(ProductOption::class);
    }

    public function connector()
    {
        return $this->belongsTo(Connector::class);
    }
}
