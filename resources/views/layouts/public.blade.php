<!doctype html>
<html lang="pt-BR">
<head>@include('layouts.head')</head>
<body class="@yield('body-class')">
<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="{{ route('home') }}">
            <img src="{{ $siteLogo??asset('assets/img/logo-painel.png') }}" alt="{{ config('app.name') }}" class="logo-img" style="height:34px;width:auto">
            <span class="brand-name">@if(config('app.name')==='LagosPanel')Lagos<b>Panel</b>@else{{ config('app.name') }}@endif</span>
        </a>
        <nav class="public-nav" aria-label="Navegação principal">
            <a href="{{ route('home') }}">Início</a><a href="{{ route('store') }}">Planos</a>
            <a href="{{ route('home') }}#experiencia">Por que escolher</a><a href="{{ route('knowledge.index') }}">Ajuda</a>
        </nav>
        <div class="header-actions" style="margin-left:auto">
            @auth
                <a class="btn btn-primary btn-sm" href="{{ route('dashboard') }}">Meu painel</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">Entrar</a>
                @if(config('site.registration_enabled'))<a href="{{ route('register') }}" class="btn btn-primary btn-sm register-shortcut">Criar conta</a>@endif
            @endauth
            <details class="public-mobile-nav">
                <summary aria-label="Abrir navegação">{!! panel_icon('menu',21) !!}</summary>
                <nav aria-label="Navegação móvel">
                    <a href="{{ route('home') }}">Início</a><a href="{{ route('store') }}">Planos</a>
                    <a href="{{ route('home') }}#experiencia">Por que escolher</a><a href="{{ route('knowledge.index') }}">Ajuda</a>
                    @if(config('site.registration_enabled'))<a href="{{ route('register') }}">Criar conta</a>@endif
                </nav>
            </details>
        </div>
    </div>
</header>
<main class="wrap site-main">
    @if(app()->environment('local'))<div class="alert alert-warning">Demonstração: use apenas dados fictícios. Os produtos de exemplo não representam recursos reais.</div>@endif
    @include('layouts.flash')
    @yield('content')
</main>
@php
    $footerExploreLinks = array_values(array_filter($siteFooterLinks ?? [], fn ($link) => ($link['group'] ?? null) === 'explore'));
    $footerInfoLinks = array_values(array_filter($siteFooterLinks ?? [], fn ($link) => ($link['group'] ?? null) === 'information'));
@endphp
<footer class="public-footer">
    <div class="wrap">
        <div class="footer-grid">
            <div class="footer-brand">
                <a class="brand" href="{{ route('home') }}">
                    <img src="{{ $siteLogo??asset('assets/img/logo-painel.png') }}" alt="{{ config('app.name') }}" class="logo-img">
                    <span class="brand-name">{{ config('app.name') }}</span>
                </a>
                @if(filled($siteFooterDescription))<p>{{ $siteFooterDescription }}</p>@endif
                @if(!empty($siteSocialLinks))
                    <nav class="social-footer-links" aria-label="Redes sociais">
                        @foreach(\App\Services\SiteConfiguration::SOCIAL_PLATFORMS as $key=>$label)
                            @if(filled($siteSocialLinks[$key]??null))
                                <a class="social-footer-link" href="{{ $siteSocialLinks[$key] }}" aria-label="{{ $label }}" title="{{ $label }}" target="_blank" rel="noopener noreferrer">
                                    {!! social_icon($key,18) !!}<span>{{ $label }}</span>
                                </a>
                            @endif
                        @endforeach
                    </nav>
                @endif
            </div>
            @if($footerExploreLinks)
                <div class="footer-column"><strong>{{ $siteFooterExploreTitle }}</strong>
                    @foreach($footerExploreLinks as $link)
                        <a href="{{ $link['url'] }}" @if(str_starts_with(strtolower($link['url']),'https://')) target="_blank" rel="noopener noreferrer" @endif>{{ $link['label'] }}</a>
                    @endforeach
                </div>
            @endif
            @if($footerInfoLinks||config('site.support_email'))
                <div class="footer-column"><strong>{{ $siteFooterInfoTitle }}</strong>
                    @foreach($footerInfoLinks as $link)
                        <a href="{{ $link['url'] }}" @if(str_starts_with(strtolower($link['url']),'https://')) target="_blank" rel="noopener noreferrer" @endif>{{ $link['label'] }}</a>
                    @endforeach
                    @if(config('site.support_email'))<a href="mailto:{{ config('site.support_email') }}">{{ config('site.support_email') }}</a>@endif
                </div>
            @endif
        </div>
        <div class="footer-bottom">
            <span>© {{ date('Y') }} {{ config('app.name') }}@if(filled($siteFooterCopyright)). {{ $siteFooterCopyright }}@endif</span>
            @if(filled($siteFooterTagline))<span>{{ $siteFooterTagline }}</span>@endif
        </div>
    </div>
</footer>
<script src="{{ panel_asset('assets/panel.js') }}" defer></script>
</body>
</html>
