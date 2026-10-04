<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DomainTld extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'register_minor' => 'integer',
            'transfer_minor' => 'integer',
            'renew_minor' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function registrations()
    {
        return $this->hasMany(DomainRegistration::class);
    }
}
