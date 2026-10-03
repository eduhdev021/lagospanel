@extends('layouts.panel')
@section('title', 'Visão geral')
@section('content')
<div class="page-head"><h2>Olá, {{ auth()->user()->name }} 👋</h2><p class="muted">Acompanhe seus serviços e mantenha tudo em dia.</p></div><div class="stats-grid">@foreach([['server',$active,'Serviços ativos'],['invoice',$open,'Faturas em aberto'],['ticket',$tickets,'Tickets abertos'],['wallet',brl(auth()->user()->balance_minor),'Saldo disponível']] as [$icon,$value,$label])<div class="stat-card"><div class="stat-ic bg-violet">{!! panel_icon($icon,23) !!}</div><div><strong>{{ $value }}</strong><span>{{ $label }}</span></div></div>@endforeach</div><div class="card"><div class="row-between"><h3 class="card-title">Meus serviços</h3><a href="{{ route('services.index') }}">Ver todos →</a></div>@include('client.service-table')<a class="btn btn-primary btn-sm" href="{{ route('store') }}">{!! panel_icon('plus',16) !!} Contratar serviço</a></div>
@endsection
