@extends('layouts.panel')
@section('title', 'Orçamento #'.$quote->id)
@section('content')
<div class="card"><h2>{{ $quote->title }}</h2><p>Cliente: {{ $quote->user->name }} · Revisão {{ $quote->version }} · {{ ['draft'=>'Rascunho','sent'=>'Disponível','accepted'=>'Aceito','declined'=>'Recusado','withdrawn'=>'Retirado'][$quote->status]??$quote->status }}</p><p>Válido até {{ $quote->valid_until->format('d/m/Y') }} ({{ config('app.timezone') }}). Pagamento até {{ $quote->payment_days }} dias após aceite.</p>
<div class="table-wrap"><table><thead><tr><th>Descrição</th><th>Quantidade</th><th>Unitário</th><th>Subtotal</th></tr></thead><tbody>@foreach($quote->items as $item)<tr><td>{{ $item['name'] }}</td><td>{{ $item['quantity'] }}</td><td>{{ brl($item['unit_minor']) }}</td><td>{{ brl($item['unit_minor']*$item['quantity']) }}</td></tr>@endforeach</tbody></table></div><h3>Total {{ brl($quote->total_minor) }}</h3><h3>Condições</h3><div style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $quote->terms }}</div><p>Proposta não fiscal de cobrança avulsa; sem ativação remota ou reserva de estoque automática.</p>
@if($quote->invoice_id)<p>Fatura gerada: #{{ $quote->invoice_id }}.</p>@if(!$admin)<a class="btn btn-primary" href="{{ route('invoices.show',$quote->invoice_id) }}">Ver fatura</a>@endif
@endif
@if($admin && auth()->user()->hasPermission('billing.manage'))
@if($quote->status==='draft')<a class="btn btn-ghost" href="{{ route('admin.quotes.edit',$quote) }}">Editar rascunho</a>@endif
@if(in_array($quote->status,['draft','sent'],true))<form method="post" action="{{ route('admin.quotes.transition',$quote) }}">@csrf<input type="hidden" name="version" value="{{ $quote->version }}">@if($quote->status==='draft'&&!$quote->expired())<button name="action" value="send" class="btn btn-primary">Disponibilizar ao cliente</button>@endif <button name="action" value="withdraw" class="btn btn-danger">Retirar proposta</button></form>@endif
@elseif(!$admin && $quote->status==='sent'&&!$quote->expired())
<form method="post" action="{{ route('quotes.decide',$quote) }}">@csrf<input type="hidden" name="version" value="{{ $quote->version }}"><p><label><input type="checkbox" name="ack" value="1" required> Li os itens e as condições. Aceitar gera uma fatura para pagamento; recusar encerra esta proposta.</label></p><button name="decision" value="accept" class="btn btn-primary">Aceitar e gerar fatura</button> <button name="decision" value="decline" class="btn btn-ghost">Recusar orçamento</button></form>
@endif
@if($quote->status==='sent'&&$quote->expired())<p class="alert alert-warning">Proposta expirada. Solicite outra à equipe.</p>@endif
</div>
@endsection
