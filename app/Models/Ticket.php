<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['response_due_at' => 'datetime', 'first_responded_at' => 'datetime', 'closed_at' => 'datetime', 'sla_hours' => 'integer'];
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class)->select(TicketAttachment::METADATA);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function replies()
    {
        return $this->hasMany(TicketReply::class);
    }
}
