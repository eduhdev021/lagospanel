@extends('layouts.panel')
@section('title', 'Administração')
@section('content')
<div class="stats-grid">@foreach([['user',$users,'Clientes'],['invoice',$unpaid,'Faturas em aberto'],['wallet',brl($paid),'Recebimentos registrados'],['alert',$review,'Operações para revisar']] as [$icon,$value,$label])<div class="stat-card"><div class="stat-ic bg-violet">{!! panel_icon($icon,23) !!}</div><div><strong>{{ $value }}</strong><span>{{ $label }}</span></div></div>@endforeach</div><div class="card"><h3>Operação da instalação</h3><p>Use a fila para notificações e integrações, e o agendador para renovações. Serviços manuais aguardam confirmação da equipe mesmo após o pagamento.</p><a class="btn btn-primary btn-sm" href="{{ route('admin.products') }}">Gerenciar produtos</a> <a class="btn btn-ghost btn-sm" href="{{ route('admin.operations') }}">Ver operações</a></div><div class="card"><h3>Últimos eventos</h3>@include('admin.event-table')</div>
@endsection
