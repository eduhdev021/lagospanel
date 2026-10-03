@extends('layouts.panel')
@section('title','Acesso inicial à hospedagem')
@section('content')
<div class="card"><h3>Acesso inicial à hospedagem</h3><p style="overflow-wrap:anywhere">Domínio: {{ $access['domain'] }}</p><p>Usuário: <code style="overflow-wrap:anywhere;white-space:normal">{{ $access['username'] }}</code></p><p>Senha inicial: <code style="overflow-wrap:anywhere;white-space:normal">{{ $access['password'] }}</code></p><p class="muted">Guarde com segurança e altere a senha no cPanel. Se já foi alterada lá, esta senha inicial não reflete a mudança. Esta página não é enviada por e-mail nem armazenada na sessão.</p><a class="btn btn-primary" href="{{ $access['url'] }}" target="_blank" rel="noopener noreferrer">Abrir cPanel</a> <a class="btn btn-ghost" href="{{ route('services.index') }}">Voltar aos serviços</a></div>
@endsection
