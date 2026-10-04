@extends('layouts.panel')
@section('title', 'Programa de afiliados')
@section('content')
@if(!$affiliate)
<div class="card">
    <h3>Indique clientes e receba comissões</h3>
    <p class="muted">Ative gratuitamente seu link de afiliado. Sempre que um cliente indicado contratar e pagar faturas elegíveis, você recebe comissão automática e pode transferir o saldo direto para sua carteira.</p>
    <form method="post" action="{{ route('affiliates.enroll') }}">
        @csrf
        <button class="btn btn-primary">Ativar minha conta de afiliado</button>
    </form>
</div>
@else
<div class="card">
    <h3>Seu painel de afiliado</h3>
    <p>Código de indicação: <code>{{ $affiliate->code }}</code> · Comissão: <strong>{{ $affiliate->rate_percent }}%</strong></p>
    <p class="muted">Link para divulgação: <code>{{ url('/loja?ref='.$affiliate->code) }}</code></p>
    <div class="form-grid">
        <div><strong>Cliques registrados:</strong> {{ $affiliate->clicks }}</div>
        <div><strong>Clientes indicados:</strong> {{ $referralsCount }}</div>
        <div><strong>Disponível para resgate:</strong> {{ brl($affiliate->available_minor) }}</div>
        <div><strong>Total acumulado:</strong> {{ brl($affiliate->total_earned_minor) }}</div>
    </div>
    <form method="post" action="{{ route('affiliates.withdraw') }}" style="margin-top:1rem">
        @csrf
        <button class="btn btn-primary btn-sm" @disabled($affiliate->available_minor < $minWithdrawal)>
            Resgatar para carteira (mínimo {{ brl($minWithdrawal) }})
        </button>
    </form>
</div>

<div class="card">
    <h3>Histórico de comissões</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Data</th><th>Fatura</th><th>Cliente indicado</th><th>Comissão</th><th>Estado</th></tr></thead>
            <tbody>
                @forelse($commissions as $c)
                <tr>
                    <td>{{ $c->created_at->format('d/m/Y') }}</td>
                    <td>#{{ $c->invoice_id }}</td>
                    <td>{{ $c->referredUser?->name ?? 'Cliente' }}</td>
                    <td>{{ brl($c->amount_minor) }}</td>
                    <td>{{ $c->status === 'credited' ? 'Creditada' : $c->status }}</td>
                </tr>
                @empty
                <tr><td colspan="5">Nenhuma comissão gerada até o momento.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
