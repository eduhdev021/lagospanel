@extends('layouts.panel')
@section('title','Página inicial')
@section('content')
@include('admin.configuration.nav')
<div class="settings-heading"><div><span class="eyebrow">SUA VITRINE DIGITAL</span><h2>Uma boa primeira impressão.</h2><p>Personalize a apresentação. Os planos vêm do catálogo, sem preços fictícios.</p></div><a class="btn btn-ghost" href="{{ route('home') }}" target="_blank" rel="noopener">{!! panel_icon('globe') !!} Ver página</a></div>
<div class="card"><form method="post" action="{{ route('admin.settings.homepage.save') }}">@csrf<input type="hidden" name="version" value="{{ $home['version']??0 }}"><fieldset @disabled(!auth()->user()->hasPermission('settings.manage'))>
@foreach(['eyebrow'=>['Chamada curta',60],'title'=>['Título principal',150],'description'=>['Apresentação',600],'cta'=>['Texto do botão de planos',40]] as $field=>[$label,$max])
<div class="field"><label for="home-{{ $field }}">{{ $label }}</label>@if($field==='description')<textarea id="home-{{ $field }}" name="{{ $field }}" maxlength="{{ $max }}" rows="4" required>{{ old($field,$home[$field]) }}</textarea>@else<input id="home-{{ $field }}" name="{{ $field }}" value="{{ old($field,$home[$field]) }}" maxlength="{{ $max }}" required>@endif</div>
@endforeach
<button class="btn btn-primary">Salvar página inicial</button></fieldset></form></div>
@endsection
