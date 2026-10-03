@extends('layouts.public')
@section('title', 'Recuperar acesso')
@section('content')
<div class="auth-wrap"><div class="auth-card"><h1 class="auth-title">Recuperar acesso</h1><p class="auth-sub">Enviaremos um link para o e-mail cadastrado.</p><form method="post" action="{{ route('password.email') }}">@csrf<div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" required></div><button class="btn btn-primary btn-block">Enviar link</button></form></div></div>
@endsection
