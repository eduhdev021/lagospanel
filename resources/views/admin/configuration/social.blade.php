@extends('layouts.panel')
@section('title','Login social')
@section('content')
@include('admin.configuration.nav')
<div class="settings-heading"><div><span class="eyebrow">ACESSO DOS CLIENTES</span><h2>Uma conta. Mais maneiras de entrar.</h2><p>Configure os aplicativos dos provedores. As credenciais são criptografadas e nunca voltam ao formulário.</p></div><span class="settings-hero-icon">{!! panel_icon('key',30) !!}</span></div>
<div class="settings-callout">{!! panel_icon('shield',22) !!}<p><strong>Clientes, não administradores.</strong> Contas existentes só são vinculadas após senha e 2FA pessoal, quando ativo. E-mails iguais nunca são unidos automaticamente.</p></div>
<div class="provider-settings-grid">
@foreach(\App\Models\SocialProvider::LABELS as $provider=>$label)
@php $setting=$settings->get($provider);$canManage=auth()->user()->hasPermission('settings.manage'); @endphp
<section class="card provider-setting"><div class="provider-top"><span class="provider-brand"><img src="{{ asset('assets/brands/'.$provider.'.svg') }}" alt="" width="28" height="28"></span><div><h3>{{ $label }}</h3><span class="status-pill {{ $setting?->enabled?'is-on':'' }}">{{ $setting?->enabled?'Habilitado':'Desativado' }}</span></div></div>
<form method="post" action="{{ route('admin.settings.social.save',$provider) }}">@csrf<input type="hidden" name="version" value="{{ $setting?->version??0 }}">
<fieldset @disabled(!$canManage)><div class="field"><label for="enabled-{{ $provider }}">Disponibilidade</label><select id="enabled-{{ $provider }}" name="enabled"><option value="0" @selected(!$setting?->enabled)>Desativado</option><option value="1" @selected($setting?->enabled)>Habilitado para clientes</option></select></div>
<div class="field"><label for="id-{{ $provider }}">Client ID / App ID</label><input id="id-{{ $provider }}" name="client_id" value="{{ $setting?->client_id }}" maxlength="255" autocomplete="off"></div>
<div class="field"><label for="secret-{{ $provider }}">Client Secret / App Secret</label><input id="secret-{{ $provider }}" name="client_secret" type="password" autocomplete="new-password" placeholder="{{ $setting?->client_secret?'Segredo salvo — deixe vazio para manter':'Informe o segredo do aplicativo' }}"><small>Nunca use seu token pessoal do GitHub neste campo.</small></div>
<label class="check-line"><input type="checkbox" name="clear_secret" value="1"> Apagar o segredo salvo (desative primeiro)</label>
<div class="callback-box"><span>URL de retorno / Redirect URI</span><code>{{ rtrim(config('app.url'),'/').route('social.callback',$provider,false) }}</code></div>
<p class="muted">@switch($provider) @case('github') Crie um OAuth App no GitHub. Escopos: read:user e user:email. @break @case('twitter') Configure um Web App OAuth 2.0 no portal X. Inclui PKCE; o acesso à API depende do plano/permissões do aplicativo. @break @case('facebook') Configure Facebook Login (Web), modo Live e URLs de privacidade/exclusão: {{ rtrim(config('app.url'),'/') }}/privacidade e {{ rtrim(config('app.url'),'/') }}/privacidade#exclusao. Solicite public_profile e email. @break @case('google') Crie um cliente OAuth Web no Google Cloud e configure a tela de consentimento. @break @case('microsoft') Registre um aplicativo Web no Microsoft Entra para contas pessoais e organizacionais (common). Permissão delegada User.Read. @break @endswitch</p>
@include('admin.webhook-confirm')<button class="btn btn-primary">Salvar {{ $label }}</button></fieldset></form></section>
@endforeach
</div>
@endsection
