<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Operation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['inspection' => 'array', 'sent_at' => 'datetime'];
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
