@extends('layouts.panel')
@section('title',$section['title'])
@section('content')
@include('admin.configuration.nav')
@php $draft=$errors->any() && old('_operation_section')===$key; @endphp
<div class="settings-heading"><div><span class="eyebrow">CONFIGURAÇÕES DA OPERAÇÃO</span><h2>{{ $section['title'] }}</h2><p>{{ $section['description'] }}</p></div><span class="settings-hero-icon">{!! panel_icon($section['icon'],30) !!}</span></div>
<nav class="operation-tabs" aria-label="Configurações operacionais">@foreach(\App\Support\OperationalSettings::SECTIONS as $id=>$item)<a class="{{ $key===$id?'is-active':'' }}" href="{{ route('admin.settings.operation',$id) }}">{!! panel_icon($item['icon'],17) !!}{{ $item['title'] }}</a>@endforeach</nav>
@if($key==='automation')<div class="settings-callout">{!! panel_icon('clock',22) !!}<p><strong>Requer scheduler e fila ativos.</strong> O comando <code>php artisan lagos:maintenance</code> aplica as regras e é agendado a cada hora. Suspensão depende do conector e do provisionamento habilitados. Desativar não cancela operações já enfileiradas. Reduzir prazos pode afetar faturas vencidas na próxima execução.</p></div>@endif
@if($key==='payments')
    <div class="settings-callout gateway-security-callout">{!! panel_icon('shield',22) !!}<p><strong>Homologação primeiro, produção depois.</strong> Pagamentos reais só são aceitos quando o ambiente e as credenciais correspondem. No Efí, a URL não é colada no site: o LagosPanel cadastra o webhook pela API Pix usando o SDK oficial, depois de o servidor estar preparado com TLS/mTLS.</p></div>
    <div class="gateway-admin-intro"><div><span class="eyebrow">CATÁLOGO DE PROVEDORES</span><h2>Escolha como seus clientes pagam<span class="heading-dot">.</span></h2><p>Um gateway por cartão, com sua marca, capacidades e campos técnicos no mesmo contexto — sem aquela lista confusa de credenciais.</p></div><span class="gateway-count">{{ collect(['stripe','mercadopago','efi'])->filter(fn($g)=>(bool)config('lagos.payments.'.$g.'.enabled'))->count() }}/3 ativos</span></div>
    <div class="card gateway-live-card"><div><strong>Permitir pagamentos reais</strong><span>Desligado mantém a operação em modo de segurança, mesmo que o gateway esteja habilitado.</span></div><div class="gateway-live-control">@include('admin.configuration.field', ['field'=>'live','label'=>'Ativar modo produção','path'=>'lagos.payments.live','type'=>'boolean','min'=>0,'max'=>1])</div></div>
    @php
        $gatewayCards = [
            'stripe' => ['name'=>'Stripe','logo'=>'stripe.svg','tone'=>'stripe','description'=>'Checkout para cartões e pagamentos internacionais.','capabilities'=>['Cartão','Checkout','Webhook'],'fields'=>['stripe_enabled','stripe_secret','stripe_webhook'],'callback'=>url('/webhooks/stripe')],
            'mercadopago' => ['name'=>'Mercado Pago','logo'=>'mercadopago.svg','tone'=>'mercadopago','description'=>'Preferência de pagamento com Pix, cartão e saldo Mercado Pago.','capabilities'=>['Pix','Cartão','Webhook'],'fields'=>['mp_enabled','mp_token'],'callback'=>url('/webhooks/mercadopago')],
            'efi' => ['name'=>'Efí Bank','logo'=>'efi.svg','tone'=>'efi','description'=>'Pix nativo via SDK oficial, QR Code, copia-e-cola e confirmação automática.','capabilities'=>['Pix SDK','OAuth2','mTLS + webhook'],'fields'=>['efi_enabled','efi_environment','efi_client_id','efi_client_secret','efi_certificate_path','efi_certificate_password','efi_certificate_type','efi_pix_key','efi_webhook_hmac','efi_charge_expiration'],'callback'=>url('/webhooks/efi?ignorar=')],
        ];
    @endphp
    <form method="post" action="{{ route('admin.settings.operation.save',$key) }}">@csrf<input type="hidden" name="version" value="{{ $draft?old('version'):($setting?->version??0) }}">
        <fieldset @disabled(!auth()->user()->hasPermission('settings.manage'))>
            <div class="gateway-admin-grid">
            @foreach($gatewayCards as $gateway=>$meta)
                @php $enabled=(bool)config('lagos.payments.'.$gateway.'.enabled'); @endphp
                <article class="gateway-admin-card gateway-tone-{{ $meta['tone'] }} {{ $enabled?'is-enabled':'' }}">
                    <header class="gateway-admin-head"><span class="gateway-admin-logo"><img src="{{ asset('assets/brands/payments/'.$meta['logo']) }}" alt="Logo {{ $meta['name'] }}"></span><div><div class="gateway-admin-name-row"><h3>{{ $meta['name'] }}</h3><span class="gateway-status {{ $enabled?'is-on':'is-off' }}"><i></i>{{ $enabled?'Ativo':'Desativado' }}</span></div><p>{{ $meta['description'] }}</p></div></header>
                    <div class="gateway-capabilities">@foreach($meta['capabilities'] as $cap)<span>{{ $cap }}</span>@endforeach</div>
                    <div class="gateway-fields">
                    @foreach($meta['fields'] as $field)
                        @php [$label,$path,$type,$min,$max]=\App\Support\OperationalSettings::SECTIONS['payments']['fields'][$field]; @endphp
                        @include('admin.configuration.field', compact('field','label','path','type','min','max'))
                    @endforeach
                    </div>
                    <div class="gateway-callback"><span>Callback</span><code>{{ $meta['callback'] }}</code></div>
                </article>
            @endforeach
            </div>
            <div class="operation-confirm gateway-confirm"><h3>Confirmar alteração</h3><p>Para salvar, informe sua senha de acesso abaixo e marque a confirmação. Estes valores substituem as opções correspondentes do ambiente, sem editar o arquivo <code>.env</code>.</p>@include('admin.webhook-confirm')<label class="check-line"><input type="checkbox" name="ack" value="1" required> Revisei os provedores, os ambientes e entendo o efeito nas próximas operações.</label><button class="btn btn-primary">Salvar gateways</button></div>
        </fieldset>
    </form>
    <div class="card gateway-reference-card"><h3>Configuração real da Efí</h3><div class="gateway-reference-grid"><div><strong>Credenciais</strong><span>Client ID, Client Secret e certificado P12/PEM por ambiente.</span></div><div><strong>Webhook via SDK</strong><span>Depois de salvar e habilitar, use o botão abaixo; o SDK chama <code>PUT /v2/webhook/{chave}</code>.</span></div><div><strong>Servidor</strong><span>TLS 1.2 e mTLS da Efí; HMAC na URL é opcional como camada adicional.</span></div></div>@if(auth()->user()->hasPermission('settings.manage'))<form method="post" action="{{ route('admin.settings.operation.efi-webhook') }}" class="inline-form">@csrf<button class="btn btn-primary btn-sm">Configurar webhook pela API Efí</button></form>@endif<p class="muted">A confirmação do navegador nunca aprova um pagamento. Consulte <a href="https://dev.efipay.com.br/docs/api-pix/webhooks/" target="_blank" rel="noopener noreferrer">a documentação oficial de webhooks da Efí</a> antes da homologação.</p></div>
@else
    @if($key==='support')<div class="settings-callout">{!! panel_icon('ticket',22) !!}<p><strong>O SLA é aplicado a novos chamados.</strong> Alterar prazos não recalcula tickets antigos; a cota da conta não remove anexos já enviados.</p></div>@endif
    <div class="card"><form method="post" action="{{ route('admin.settings.operation.save',$key) }}">@csrf<input type="hidden" name="version" value="{{ $draft?old('version'):($setting?->version??0) }}"><fieldset @disabled(!auth()->user()->hasPermission('settings.manage'))><div class="operation-fields">@foreach($section['fields'] as $field=>[$label,$path,$type,$min,$max]) @include('admin.configuration.field', compact('field','label','path','type','min','max')) @endforeach</div><div class="operation-confirm"><h3>Confirmar alteração</h3><p>Para salvar, informe sua senha de acesso abaixo e marque a confirmação. Isso não altera sua senha.</p>@include('admin.webhook-confirm')<label class="check-line"><input type="checkbox" name="ack" value="1" required> Revisei os valores e entendo o efeito nas próximas operações.</label><button class="btn btn-primary">Salvar configurações</button></div></fieldset></form></div>
@endif
@if($key==='support')<div class="card"><h3>Outras opções de atendimento</h3><p>Os departamentos atuais são Suporte e Financeiro. Os limites por arquivo continuam em 2 MiB e 3 anexos por mensagem. Alterar a cota da conta não remove anexos existentes.</p>@if(auth()->user()->hasPermission('support.view'))<a class="btn btn-ghost" href="{{ route('admin.tickets.templates') }}">Respostas prontas</a>@endif</div>@endif
@endsection
