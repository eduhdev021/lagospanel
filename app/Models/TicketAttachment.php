<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketAttachment extends Model
{
    protected $guarded = [];

    protected $hidden = ['payload'];

    public const METADATA = ['id', 'ticket_id', 'ticket_reply_id', 'filename', 'mime', 'size', 'is_internal', 'created_at'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted', 'is_internal' => 'boolean', 'size' => 'integer'];
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
