@extends('layouts.panel')
@section('title', 'Faturamento')
@section('content')
<div class="card">
    <p class="muted">Registre apenas recebimentos conferidos. Estorno e chargeback alteram o ledger interno e precisam também ser executados ou conferidos no provedor externo.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Fatura / cliente</th><th>Valor</th><th>Status</th><th>Pagamentos e operação financeira</th></tr></thead>
            <tbody>
            @forelse($invoices as $i)
                <tr>
                    <td>#{{ $i->id }} · {{ $i->user->name }}<br><small>{{ $i->due_date->format('d/m/Y') }}</small><br><a href="{{ route('admin.invoices.pdf',$i) }}">PDF</a></td>
                    <td>{{ brl($i->total_minor) }}<br><small>Pago: {{ brl($i->paid_minor) }}</small></td>
                    <td><span class="badge badge-{{ $i->status }}">{{ status_label($i->status) }}</span></td>
                    <td>
                        @if(in_array($i->status,['unpaid','overdue']) && auth()->user()->hasPermission('billing.manage'))
                            <form class="inline-form" method="post" action="{{ route('admin.invoices.paid',$i) }}">@csrf<input name="note" type="text" placeholder="Referência / comprovante" aria-label="Referência do recebimento" required minlength="5" maxlength="500"><button class="btn btn-primary btn-sm">Confirmar recebimento</button></form>
                        @endif
                        @forelse($i->payments as $payment)
                            @php $available = max(0, $payment->amount_minor - $payment->refunded_minor); @endphp
                            <details class="finance-payment">
                                <summary>{{ strtoupper($payment->gateway) }} · {{ brl($payment->amount_minor) }} · {{ $payment->status }}</summary>
                                <p class="muted">Referência: <code>{{ $payment->reference }}</code> · Já estornado: {{ brl($payment->refunded_minor) }} · Disponível: {{ brl($available) }}</p>
                                @if(auth()->user()->hasPermission('billing.manage') && $available > 0 && $payment->status !== 'chargeback')
                                    <div class="form-grid">
                                        <form method="post" action="{{ route('admin.invoices.refund',$payment) }}">@csrf<h4>Registrar estorno</h4><label>Valor em centavos<input type="number" name="amount_minor" min="1" max="{{ $available }}" value="{{ $available }}" required></label><label>Referência interna<input name="reference" pattern="[a-zA-Z0-9._:-]+" maxlength="190" required></label><label>Referência do provedor<input name="provider_reference" maxlength="190"></label><label>Motivo<textarea name="reason" maxlength="500" required minlength="5"></textarea></label><button class="btn btn-ghost btn-sm">Registrar estorno no ledger</button></form>
                                        <form method="post" action="{{ route('admin.invoices.chargeback',$payment) }}">@csrf<h4>Registrar chargeback</h4><label>Valor em centavos<input type="number" name="amount_minor" min="1" max="{{ $available }}" value="{{ $available }}" required></label><label>Referência interna<input name="reference" pattern="[a-zA-Z0-9._:-]+" maxlength="190" required></label><label>Referência do provedor<input name="provider_reference" maxlength="190"></label><label>Motivo<textarea name="reason" maxlength="500" required minlength="5"></textarea></label><button class="btn btn-danger btn-sm">Registrar chargeback</button></form>
                                    </div>
                                @endif
                            </details>
                        @empty
                            @if(!in_array($i->status,['unpaid','overdue']))<span class="muted">Nenhum pagamento detalhado.</span>@endif
                        @endforelse
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">Nenhuma fatura encontrada.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $invoices->links('layouts.pagination') }}
@endsection
