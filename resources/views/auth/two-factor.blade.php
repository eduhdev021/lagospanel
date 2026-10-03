@extends('layouts.public')
@section('title','Autenticação em duas etapas')
@section('content')<div class="auth-wrap"><div class="auth-card"><h1 class="auth-title">Autenticação em duas etapas</h1><p class="auth-sub">Informe o código do autenticador ou um código de recuperação.</p><form method="post" action="{{ route('two-factor.confirm') }}">@csrf<div class="field"><label for="code">Código</label><input name="code" id="code" type="text" required autocomplete="one-time-code"></div><button class="btn btn-primary btn-block">Confirmar acesso</button></form></div></div>@endsection
