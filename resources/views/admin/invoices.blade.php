@extends('layouts.panel')
@section('title', 'Faturamento')
@section('content')
<div class="card"><p class="muted">Registre apenas recebimentos conferidos. O registro financeiro não pode ser editado por esta tela.</p><div class="table-wrap"><table><thead><tr><th>Fatura / cliente</th><th>Valor</th><th>Status</th><th>Conferência</th></tr></thead><tbody>@foreach($invoices as $i)<tr><td>#{{ $i->id }} · {{ $i->user->name }}<br><small>{{ $i->due_date->format('d/m/Y') }}</small><br><a href="{{ route('admin.invoices.pdf',$i) }}">PDF</a></td><td>{{ brl($i->total_minor) }}</td><td><span class="badge badge-{{ $i->status }}">{{ status_label($i->status) }}</span></td><td>@if(in_array($i->status,['unpaid','overdue']))@if(auth()->user()->hasPermission('billing.manage'))
<form class="inline-form" method="post" action="{{ route('admin.invoices.paid',$i) }}">@csrf<input name="note" type="text" placeholder="Referência / comprovante" aria-label="Referência do recebimento" required minlength="5" maxlength="500"><button class="btn btn-primary btn-sm">Confirmar recebimento</button></form>
@endif
@else—@endif</td></tr>@endforeach</tbody></table></div></div>{{ $invoices->links('layouts.pagination') }}
@endsection
