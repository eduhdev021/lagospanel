@extends('layouts.public')
@section('title', 'Nova senha')
@section('content')
<div class="auth-wrap"><div class="auth-card"><h1 class="auth-title">Defina sua nova senha</h1><form method="post" action="{{ route('password.update') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><div class="field"><label>E-mail</label><input type="email" name="email" value="{{ $email }}" required></div><div class="field"><label>Senha (12 caracteres, letras e números)</label><input type="password" name="password" required minlength="12" autocomplete="new-password"></div><div class="field"><label>Confirmar senha</label><input type="password" name="password_confirmation" required autocomplete="new-password"></div><button class="btn btn-primary btn-block">Salvar nova senha</button></form></div></div>
@endsection
