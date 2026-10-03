<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiToken extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'expires_at' => 'datetime', 'last_used_at' => 'datetime'];
    }

    protected $hidden = ['token_hash', 'password_fingerprint'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
