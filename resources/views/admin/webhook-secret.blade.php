@extends('layouts.panel')
@section('title','Chave de assinatura do webhook')
@section('content')
<div class="card"><h2>{{ $endpoint->name }}</h2><p>Esta chave é mostrada somente agora. Guarde-a no servidor destinatário; não a inclua na URL, em JavaScript público ou em chamados.</p><p style="overflow-wrap:anywhere">{{ $secret }}</p><p>Assinatura: HMAC-SHA256 de timestamp + ponto + bytes exatos do JSON, usando esta chave como texto ASCII. Consulte docs/WEBHOOKS-SAIDA.md.</p><a class="btn btn-primary" href="{{ route('admin.webhooks.index') }}">Voltar aos webhooks</a></div>
@endsection
