@extends('layouts.panel')
@section('title', 'Suporte')
@section('content')
@if(!isset($threadReplies))
<div class="card"><h3 class="card-title">Abrir ticket</h3><form method="post" enctype="multipart/form-data" action="{{ route('tickets.create') }}">@csrf
<div class="form-grid"><div class="field"><label>Assunto</label><input name="subject" type="text" required maxlength="180"></div><div class="field"><label>Departamento</label><select name="department"><option value="support">Suporte técnico</option><option value="billing">Financeiro</option></select></div><div class="field"><label>Prioridade</label><select name="priority">@foreach(\App\Services\SupportDesk::LABELS as $key=>$label)<option value="{{ $key }}" @selected($key==='normal')>{{ $label }}</option>@endforeach</select></div></div>
<div class="field"><label>Mensagem</label><textarea name="body" required maxlength="10000"></textarea></div>@include('support.upload')<button class="btn btn-primary">Enviar ticket</button></form></div>
@endif
@foreach($tickets as $t)<div class="card" id="ticket-{{ $t->id }}"><div class="row-between"><h3>#{{ $t->id }} — {{ $t->subject }}</h3><span class="badge badge-{{ $t->status }}">{{ status_label($t->status) }}</span></div>
@include('support.deadline')
<p class="preserve">{{ $t->body }}</p>@include('support.attachments',['files'=>$t->attachments,'admin'=>false])
@foreach($t->replies->sortBy('id') as $reply)<div class="ticket-message"><strong>{{ $reply->user->name }}</strong><small> · {{ $reply->created_at->format('d/m H:i') }}</small><p class="preserve">{{ $reply->body }}</p>@include('support.attachments',['files'=>$reply->attachments,'admin'=>false])</div>@endforeach
@if(isset($threadReplies)) {{ $threadReplies->links('layouts.pagination') }} @else <a class="btn btn-ghost btn-sm" href="{{ route('tickets.show',$t) }}">Histórico completo</a> @endif
@if($t->status!=='closed')<form method="post" enctype="multipart/form-data" action="{{ route('tickets.reply',$t) }}">@csrf<div class="field"><label>Responder</label><textarea name="body" required maxlength="10000"></textarea></div>@include('support.upload')<button class="btn btn-ghost btn-sm">Enviar resposta</button></form>@endif
<form method="post" action="{{ route('tickets.state',$t) }}">@csrf<input type="hidden" name="status" value="{{ $t->status==='closed'?'open':'closed' }}"><button class="btn btn-ghost btn-sm">{{ $t->status==='closed'?'Reabrir chamado':'Encerrar chamado' }}</button></form>
</div>@endforeach
{{ $tickets->links('layouts.pagination') }}
@endsection
