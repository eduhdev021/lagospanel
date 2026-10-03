<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiSetting extends Model
{
    protected $table = 'ai_settings';

    protected $guarded = [];

    protected $hidden = ['token', 'web_token'];

    protected function casts(): array
    {
        return ['web_token' => 'encrypted', 'web_enabled' => 'boolean', 'token' => 'encrypted', 'models' => 'array', 'active' => 'boolean', 'version' => 'integer', 'models_checked_at' => 'datetime'];
    }
}
