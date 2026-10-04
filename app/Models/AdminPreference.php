<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminPreference extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['require_two_factor' => 'boolean', 'version' => 'integer', 'updater_heartbeat_at' => 'datetime'];
    }

    public static function ensure(): void
    {
        self::query()->insertOrIgnore(['id' => 1, 'version' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }
}
