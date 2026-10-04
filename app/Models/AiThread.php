<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AiThread extends Model
{
    protected $guarded = [];

    protected $hidden = ['title', 'creation_key'];

    protected function casts(): array
    {
        return ['title' => 'encrypted', 'consented_at' => 'datetime'];
    }

    public function firstTurn()
    {
        return $this->hasOne(AiTurn::class)->oldestOfMany();
    }

    public function displayTitle(): string
    {
        return $this->title ?: Str::limit($this->firstTurn?->user_text ?? 'Nova conversa', 55);
    }

    public function turns()
    {
        return $this->hasMany(AiTurn::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
