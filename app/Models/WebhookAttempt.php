<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookAttempt extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['execution_token'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
