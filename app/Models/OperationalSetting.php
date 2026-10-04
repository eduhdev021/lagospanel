<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalSetting extends Model
{
    protected $primaryKey = 'section';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['values'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'values' => 'encrypted:array'];
    }
}
