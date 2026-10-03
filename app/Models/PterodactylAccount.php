<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PterodactylAccount extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['remote_user_id' => 'integer'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
