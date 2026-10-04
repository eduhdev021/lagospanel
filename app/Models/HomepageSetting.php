<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageSetting extends Model
{
    public const DEFAULTS = ['eyebrow' => 'SEU PRÓXIMO PROJETO COMEÇA AQUI', 'title' => 'Grandes ideias merecem um lugar para crescer.', 'description' => 'Encontre a hospedagem para o seu próximo passo. Contrate planos e acompanhe seus serviços, faturas e atendimento em um só lugar.', 'cta' => 'Conhecer os planos'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }
}
