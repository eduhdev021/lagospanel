@php
    $siteMetaDescription ??= 'Planos de hospedagem, domínios e suporte para manter seus projetos online.';
    $siteBrandColor ??= '#7C3AED';
    $siteAccentColor ??= '#C040E0';
    $pageTitle = trim(strip_tags($__env->yieldContent('title')));
    $pageTitle = $pageTitle !== '' ? $pageTitle : config('app.name');
    $metaDescription = trim(strip_tags($__env->yieldContent('meta-description', $siteMetaDescription)));
    $metaDescription = $metaDescription !== '' ? $metaDescription : $siteMetaDescription;
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $pageTitle }}@if($pageTitle!==config('app.name')) · {{ config('app.name') }}@endif</title>
<meta name="description" content="{{ $metaDescription }}">
<meta name="theme-color" content="{{ $siteBrandColor }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:locale" content="pt_BR">
<meta property="og:title" content="{{ $pageTitle }}@if($pageTitle!==config('app.name')) · {{ config('app.name') }}@endif">
<meta property="og:description" content="{{ $metaDescription }}">
<meta property="og:url" content="{{ url()->current() }}">
<link rel="canonical" href="{{ url()->current() }}">
<meta property="og:image" content="{{ $siteOpenGraphImage??$siteLogo??asset('assets/img/logo-painel.png') }}">
<meta property="og:image:alt" content="{{ config('app.name') }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $pageTitle }}@if($pageTitle!==config('app.name')) · {{ config('app.name') }}@endif">
<meta name="twitter:description" content="{{ $metaDescription }}">
<meta name="twitter:image" content="{{ $siteOpenGraphImage??$siteLogo??asset('assets/img/logo-painel.png') }}">
<link rel="icon" href="{{ $siteFavicon??asset('assets/img/favicon.png') }}">
<link rel="apple-touch-icon" href="{{ $siteFavicon??asset('assets/img/favicon.png') }}">
<link rel="stylesheet" href="{{ panel_asset('assets/panel.css') }}">
<link rel="stylesheet" href="{{ panel_asset('assets/independent.css') }}">
<link rel="stylesheet" href="{{ panel_asset('assets/experience.css') }}">
<style>:root{--brand:{{ $siteBrandColor }};--brand-2:{{ $siteAccentColor }};--brand-deep:{{ $siteBrandColor }};--blue:{{ $siteBrandColor }};--violet:{{ $siteBrandColor }};--blue-bg:color-mix(in srgb,{{ $siteBrandColor }} 9%,#fff);--violet-bg:color-mix(in srgb,{{ $siteBrandColor }} 9%,#fff)}</style>
