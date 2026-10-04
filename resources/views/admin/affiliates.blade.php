@extends('layouts.panel')
@section('title', 'Afiliados e comissões')
@section('content')
<div class="card">
    <h3>Afiliados cadastrados</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Cliente</th><th>Código</th><th>Comissão (%)</th><th>Cliques</th><th>Saldo disponível</th><th>Total ganho</th><th>Ação</th></tr></thead>
            <tbody>
                @forelse($affiliates as $a)
                <tr>
                    <td>{{ $a->user?->email }}</td>
                    <td><code>{{ $a->code }}</code></td>
                    <td>{{ $a->rate_percent }}%</td>
                    <td>{{ $a->clicks }}</td>
                    <td>{{ brl($a->available_minor) }}</td>
                    <td>{{ brl($a->total_earned_minor) }}</td>
                    <td>
                        @if(auth()->user()->hasPermission('billing.manage'))
                        <form method="post" action="{{ route('admin.affiliates.update', $a) }}" style="display:flex;gap:0.5rem;align-items:center">
                            @csrf
                            <input type="number" name="rate_percent" value="{{ $a->rate_percent }}" min="1" max="80" style="width:70px">
                            <label><input type="checkbox" name="active" value="1" @checked($a->active)> Ativo</label>
                            <button class="btn btn-ghost btn-sm">Salvar</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7">Nenhum afiliado inscrito.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3>Últimas comissões creditadas</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Data</th><th>Afiliado</th><th>Cliente indicado</th><th>Fatura</th><th>Valor</th></tr></thead>
            <tbody>
                @forelse($commissions as $c)
                <tr>
                    <td>{{ $c->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $c->affiliate?->user?->email }} ({{ $c->affiliate?->code }})</td>
                    <td>{{ $c->referredUser?->email }}</td>
                    <td>#{{ $c->invoice_id }}</td>
                    <td>{{ brl($c->amount_minor) }}</td>
                </tr>
                @empty
                <tr><td colspan="5">Nenhuma comissão registrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $affiliates->links('layouts.pagination') }}
@endsection
