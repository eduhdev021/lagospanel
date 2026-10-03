<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOption extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['required' => 'boolean', 'active' => 'boolean'];
    }

    public function values()
    {
        return $this->hasMany(OptionValue::class);
    }
}
