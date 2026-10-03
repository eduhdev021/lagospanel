@extends('layouts.panel')
@section('title', 'Orçamentos')
@section('content')
<div class="card"><h2>Orçamentos</h2><p>Propostas de cobrança avulsa. O aceite gera uma fatura única; não cria hospedagem, reserva estoque ou altera serviços automaticamente.</p>
@if($admin && auth()->user()->hasPermission('billing.manage'))<a class="btn btn-primary" href="{{ route('admin.quotes.new') }}">Novo orçamento</a>@endif
<div class="table-wrap"><table><thead><tr><th>Proposta</th>@if($admin)<th>Cliente</th>@endif<th>Total</th><th>Estado</th><th>Validade</th></tr></thead><tbody>
@forelse($quotes as $quote)<tr><td><a href="{{ route($admin?'admin.quotes.show':'quotes.show',$quote) }}">#{{ $quote->id }} · {{ $quote->title }}</a></td>@if($admin)<td>{{ $quote->user->name }}</td>@endif<td>{{ brl($quote->total_minor) }}</td><td>{{ ['draft'=>'Rascunho','sent'=>'Disponível','accepted'=>'Aceito','declined'=>'Recusado','withdrawn'=>'Retirado'][$quote->status]??$quote->status }}{{ $quote->status==='sent'&&$quote->expired()?' (expirado)':'' }}</td><td>{{ $quote->valid_until->format('d/m/Y') }}</td></tr>
@empty<tr><td colspan="5">Nenhum orçamento disponível.</td></tr>@endforelse
</tbody></table></div>{{ $quotes->links('layouts.pagination') }}</div>
@endsection
