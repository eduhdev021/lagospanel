<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $guarded = [];

    protected $hidden = ['smtp_password', 'logo_content'];

    protected function casts(): array
    {
        return ['smtp_password' => 'encrypted', 'registration_enabled' => 'boolean', 'version' => 'integer', 'smtp_port' => 'integer', 'social_links' => 'array'];
    }
}
