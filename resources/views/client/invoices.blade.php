@extends('layouts.panel')
@section('title', 'Minhas faturas')
@section('content')
<div class="card"><div class="table-wrap"><table><thead><tr><th>Fatura</th><th>Vencimento</th><th>Total</th><th>Status</th><th></th></tr></thead><tbody>@forelse($invoices as $i)<tr><td>#{{ $i->id }}</td><td>{{ $i->due_date->format('d/m/Y') }}</td><td>{{ brl($i->total_minor) }}</td><td><span class="badge badge-{{ $i->status }}">{{ status_label($i->status) }}</span></td><td><a class="btn btn-ghost btn-sm" href="{{ route('invoices.show',$i) }}">Ver fatura</a></td></tr>@empty<tr><td colspan="5">Nenhuma fatura encontrada.</td></tr>@endforelse</tbody></table></div></div>{{ $invoices->links('layouts.pagination') }}
@endsection
