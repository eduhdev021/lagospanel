@extends('layouts.panel')
@section('title', 'Atendimento')
@section('content')
<p><a class="btn btn-ghost" href="{{ route('admin.tickets.templates') }}">Respostas prontas</a></p>
@if(!isset($threadReplies))
<div class="card"><form method="get" class="form-grid"><div class="field"><label>Estado</label><select name="status"><option value="">Todos</option>@foreach(['open','customer_reply','answered','closed'] as $state)<option value="{{ $state }}" @selected(request('status')===$state)>{{ status_label($state) }}</option>@endforeach</select></div><div class="field"><label>Departamento</label><select name="department"><option value="">Todos</option><option value="support" @selected(request('department')==='support')>Suporte técnico</option><option value="billing" @selected(request('department')==='billing')>Financeiro</option></select></div><div class="field"><label>Prioridade</label><select name="priority"><option value="">Todas</option>@foreach(\App\Services\SupportDesk::LABELS as $key=>$label)<option value="{{ $key }}" @selected(request('priority')===$key)>{{ $label }}</option>@endforeach</select></div><label><input type="checkbox" name="overdue" value="1" @checked(request('overdue'))> Prazo ultrapassado</label><label><input type="checkbox" name="mine" value="1" @checked(request('mine'))> Atribuídos a mim</label><button class="btn btn-ghost">Filtrar</button></form></div>
@endif
@forelse($tickets as $t)<div class="card" id="ticket-{{ $t->id }}"><div class="row-between"><h3>#{{ $t->id }} {{ $t->subject }}</h3><span class="badge badge-{{ $t->status }}">{{ status_label($t->status) }}</span></div><small>{{ $t->user->name }} · {{ $t->department }} · Responsável: {{ $t->assignee?->name ?? 'Não atribuído' }}</small>
@include('support.deadline')
<p class="preserve">{{ $t->body }}</p>@include('support.attachments',['files'=>$t->attachments,'admin'=>true])
@foreach($t->replies->sortBy('id') as $reply)<div class="ticket-message"><strong>{{ $reply->user->name }}</strong>@if($reply->is_internal)<span class="badge">Nota interna — equipe</span>@endif<small> · {{ $reply->created_at->format('d/m H:i') }}</small><p class="preserve">{{ $reply->body }}</p>@include('support.attachments',['files'=>$reply->attachments,'admin'=>true])</div>@endforeach
@if(isset($threadReplies)) {{ $threadReplies->links('layouts.pagination') }} @else <a class="btn btn-ghost btn-sm" href="{{ route('admin.tickets.show',$t) }}">Histórico completo</a> @endif
@if(auth()->user()->hasPermission('support.manage'))
<form method="post" action="{{ route('admin.tickets.triage',$t) }}">@csrf<div class="form-grid"><div class="field"><label>Prioridade</label><select name="priority">@foreach(\App\Services\SupportDesk::LABELS as $key=>$label)<option value="{{ $key }}" @selected($t->priority===$key)>{{ $label }}</option>@endforeach</select></div><div class="field"><label>Responsável</label><select name="assigned_to"><option value="">Não atribuído</option>@foreach($staff as $agent)<option value="{{ $agent->id }}" @selected($t->assigned_to===$agent->id)>{{ $agent->name }}</option>@endforeach</select></div></div><button class="btn btn-ghost btn-sm">Salvar triagem</button></form>
<form method="post" enctype="multipart/form-data" action="{{ route('admin.tickets.reply',$t) }}">@csrf
@include('support.canned')
<div class="field"><label>Resposta</label><textarea name="body" required maxlength="10000"></textarea></div><div class="field"><label>Estado após responder</label><select name="status"><option value="answered">Respondido</option><option value="closed">Encerrado</option></select></div><label><input type="checkbox" name="internal" value="1"> Nota interna (não altera estado nem prazo; não envia e-mail)</label>@include('support.upload')<button class="btn btn-primary btn-sm">Enviar resposta</button></form>
@endif
</div>@empty<div class="card">Nenhum ticket.</div>@endforelse
{{ $tickets->links('layouts.pagination') }}
<script src="{{ asset('assets/support-templates.js') }}" defer></script>
@endsection
