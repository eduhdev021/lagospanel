<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    protected $guarded = [];

    protected $hidden = ['payload', 'execution_token'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted', 'attempts' => 'integer', 'max_attempts' => 'integer', 'retry_base' => 'integer', 'started_at' => 'datetime', 'next_attempt_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function endpoint()
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    public function history()
    {
        return $this->hasMany(WebhookAttempt::class);
    }
}
