<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PleskCustomerRequest extends Model
{
    protected $guarded = [];

    protected $hidden = ['secret', 'execution_token'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'remote_id' => 'integer', 'started_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
