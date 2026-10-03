<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketReply extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean', 'is_staff' => 'boolean'];
    }

    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class)->select(TicketAttachment::METADATA);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
