<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'snapshot' => 'array', 'total_minor' => 'integer', 'due_date' => 'immutable_date', 'period_start' => 'immutable_date', 'paid_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
