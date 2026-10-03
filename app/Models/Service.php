<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $guarded = [];

    protected $hidden = ['provisioning_secret', 'provisioning'];

    protected function casts(): array
    {
        return ['provisioning' => 'array', 'provisioning_secret' => 'encrypted', 'configuration' => 'array', 'next_due' => 'immutable_date', 'price_minor' => 'integer', 'auto_renew' => 'boolean', 'cancellation_requested_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function connector()
    {
        return $this->belongsTo(Connector::class);
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class);
    }

    public function operations()
    {
        return $this->hasMany(Operation::class);
    }
}
