<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiTurn extends Model
{
    protected $guarded = [];

    protected $hidden = ['user_text', 'assistant_text', 'execution_token', 'web_query', 'web_sources'];

    protected function casts(): array
    {
        return ['web_requested' => 'boolean', 'web_query' => 'encrypted', 'web_sources' => 'encrypted:array', 'user_text' => 'encrypted', 'assistant_text' => 'encrypted', 'settings_version' => 'integer', 'started_at' => 'datetime'];
    }

    public function thread()
    {
        return $this->belongsTo(AiThread::class, 'ai_thread_id');
    }
}
