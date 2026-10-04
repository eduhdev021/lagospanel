<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanelUpdate extends Model
{
    protected $guarded = [];

    protected $hidden = ['backup_path'];

    protected function casts(): array
    {
        return ['events' => 'array', 'approved_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function record(string $phase): void
    {
        $this->refresh();
        $events = $this->events ?? [];
        $events[] = ['at' => now()->toIso8601String(), 'phase' => $phase];
        $this->update(['phase' => $phase, 'events' => $events]);
    }
}
