<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bulletin extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['published' => 'boolean', 'published_at' => 'datetime', 'version' => 'integer'];
    }

    public function updates()
    {
        return $this->hasMany(BulletinUpdate::class);
    }

    public function scopeVisible($q)
    {
        return $q->where('published', true)->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
