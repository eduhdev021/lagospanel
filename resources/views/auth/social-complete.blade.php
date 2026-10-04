@extends('layouts.public')
@section('title','Concluir cadastro')
@section('content')
<div class="auth-wrap"><div class="auth-card"><span class="eyebrow">QUASE PRONTO</span><h1 class="auth-title">Seu espaço começa aqui.</h1><p>Identidade confirmada no {{ \App\Models\SocialProvider::LABELS[$pending['provider']] }}. Confirme seus dados e defina uma senha de recuperação. Enviaremos a confirmação para seu e-mail.</p><div class="alert alert-warning">Já possui conta? <a href="{{ route('login') }}">Entre com sua senha</a> e vincule o provedor em Minha conta. Não unimos contas automaticamente por e-mail.</div><form method="post" action="{{ route('social.register') }}">@csrf
<div class="field"><label for="social-name">Nome</label><input id="social-name" name="name" value="{{ old('name',$pending['name']) }}" maxlength="100" autocomplete="name" required></div>
<div class="field"><label for="social-email">E-mail de contato</label><input id="social-email" name="email" type="email" value="{{ old('email',$pending['email']) }}" maxlength="254" autocomplete="email" required></div>
<div class="field"><label for="social-password">Senha de recuperação (12 caracteres, letras e números)</label><input id="social-password" name="password" type="password" minlength="12" maxlength="128" autocomplete="new-password" required></div>
<div class="field"><label for="social-confirm">Confirme a senha</label><input id="social-confirm" name="password_confirmation" type="password" autocomplete="new-password" required></div>
<label class="check-line"><input name="terms" type="checkbox" value="1" required> Li as <a href="{{ route('terms') }}">informações e condições da instalação</a> e a <a href="{{ route('privacy') }}">política de login social</a>.</label><button class="btn btn-primary btn-block">Criar minha conta</button></form></div></div>
@endsection
