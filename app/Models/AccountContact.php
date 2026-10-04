<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountContact extends Model
{
    public const PERMISSIONS = [
        'invoices.view' => 'Visualizar faturas',
        'invoices.pay' => 'Pagar faturas com saldo/gateway',
        'services.view' => 'Visualizar serviços e planos',
        'domains.manage' => 'Gerenciar domínios e nameservers',
        'tickets.manage' => 'Abrir e responder chamados de suporte',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'receive_billing_emails' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function contactUser()
    {
        return $this->belongsTo(User::class, 'contact_user_id');
    }
}
