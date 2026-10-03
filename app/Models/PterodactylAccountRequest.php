<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PterodactylAccountRequest extends Model
{
    protected $guarded = [];

    protected $hidden = ['execution_token'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'sent_at' => 'datetime', 'remote_user_id' => 'integer'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
