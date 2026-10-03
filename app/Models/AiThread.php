<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiThread extends Model
{
    protected $guarded = [];

    public function turns()
    {
        return $this->hasMany(AiTurn::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
