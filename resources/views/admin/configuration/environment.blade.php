@extends('layouts.panel')
@section('title','Configurações — ambiente')
@section('content')
@include('admin.configuration.nav')
<div class="card"><h2>Ambiente e recursos</h2><p>Estas opções pertencem à configuração do servidor. O painel não sobrescreve o .env nem exibe credenciais. Alterar uma integração individual não habilita o bloqueio global automaticamente.</p><div class="table-wrap"><table><thead><tr><th>Recurso</th><th>Estado</th><th>Configuração</th></tr></thead><tbody>
<tr><td>Versão instalada</td><td>{{ config('lagos.version') }}</td><td>Atualizações na seção dedicada</td></tr><tr><td>PHP</td><td>{{ PHP_VERSION }}</td><td>PHP 8.2 ou superior</td></tr><tr><td>Banco</td><td>{{ config('database.default') }}</td><td>DB_CONNECTION</td></tr><tr><td>Fila</td><td>{{ config('queue.default') }}</td><td>Worker e cron devem estar em execução</td></tr>
@foreach(['Provisionamento nativo'=>['lagos.native_provisioning','NATIVE_PROVISIONING_ENABLED'],'Pagamentos reais'=>['lagos.payments.live','PAYMENTS_LIVE'],'Stripe'=>['lagos.payments.stripe.enabled','STRIPE_ENABLED'],'Mercado Pago'=>['lagos.payments.mercadopago.enabled','MP_ENABLED'],'Webhooks de saída'=>['lagos.outgoing_webhooks','OUTGOING_WEBHOOKS_ENABLED'],'Atualizador'=>['panel_updates.enabled','PANEL_UPDATES_ENABLED']] as $name=>[$key,$variable])<tr><td>{{ $name }}</td><td>{{ config($key)?'Habilitado':'Desabilitado' }}</td><td><code>{{ $variable }}</code></td></tr>@endforeach
</tbody></table></div><p>Após alterar o ambiente, limpe/refaça o cache de configuração e reinicie os workers. Nunca gere outra APP_KEY sobre uma instalação existente.</p></div>
@endsection
