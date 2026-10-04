<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAddon extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['price_minor' => 'integer', 'setup_minor' => 'integer', 'active' => 'boolean'];
    }

    public function serviceAddons()
    {
        return $this->hasMany(ServiceAddon::class);
    }
}
