<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialProvider extends Model
{
    public const LABELS = ['github' => 'GitHub', 'twitter' => 'X / Twitter', 'facebook' => 'Facebook', 'google' => 'Google', 'microsoft' => 'Microsoft'];

    protected $primaryKey = 'provider';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['client_secret'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'version' => 'integer', 'client_secret' => 'encrypted'];
    }

    public function fingerprint(): string
    {
        return hash('sha256', (string) $this->client_id);
    }
}
