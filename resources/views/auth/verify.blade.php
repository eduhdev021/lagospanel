@extends('layouts.public')
@section('title', 'Confirme seu e-mail')
@section('content')
<div class="auth-wrap"><div class="auth-card"><h1 class="auth-title">Confirme seu e-mail</h1><p class="auth-sub">Abra o link enviado ao endereço da sua conta para acessar serviços e pagamentos.</p><form method="post" action="{{ route('verification.send') }}">@csrf<button class="btn btn-primary btn-block">Reenviar confirmação</button></form><br><form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-ghost btn-block">Sair</button></form></div></div>
@endsection
