<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialIdentity extends Model
{
    protected $guarded = [];

    protected $hidden = ['subject'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
