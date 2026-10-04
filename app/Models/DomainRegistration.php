<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DomainRegistration extends Model
{
    protected $guarded = [];

    protected $hidden = ['epp_code'];

    protected function casts(): array
    {
        return [
            'years' => 'integer',
            'renew_minor' => 'integer',
            'transfer_lock' => 'boolean',
            'epp_code' => 'encrypted',
            'nameservers' => 'array',
            'expires_at' => 'immutable_date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tld()
    {
        return $this->belongsTo(DomainTld::class, 'domain_tld_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
