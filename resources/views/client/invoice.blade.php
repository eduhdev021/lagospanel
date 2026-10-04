@extends('layouts.panel')
@section('title', 'Detalhes da fatura')
@section('content')
<div class="card invoice-card">
    <p><a class="btn btn-ghost btn-sm" href="{{ route('invoices.pdf',$invoice) }}">Baixar fatura em PDF</a></p>
    <div class="row-between"><h2>Fatura #{{ $invoice->id }}</h2><span class="badge badge-{{ $invoice->status }}">{{ status_label($invoice->status) }}</span></div>
    <p class="muted">Vencimento: {{ $invoice->due_date->format('d/m/Y') }} · BRL</p>
    <div class="table-wrap"><table><thead><tr><th>Descrição</th><th>Quantidade</th><th>Valor unitário</th></tr></thead><tbody>
    @foreach($invoice->snapshot as $item)
        <tr><td>{{ $item['name'] ?? 'Item importado' }}@foreach($item['configuration']??[] as $c)<br><small>{{ $c['name'] }}: {{ $c['label'] }}</small>@endforeach @if(!empty($item['setup_minor']))<br><small>Instalação: {{ brl($item['setup_minor']) }}</small>@endif @if(!empty($item['discount_minor']))<br><small>Desconto inicial: {{ brl($item['discount_minor']) }}</small>@endif</td><td>{{ $item['quantity'] ?? 1 }}</td><td>{{ brl((int)($item['unit_minor'] ?? 0)) }}</td></tr>
    @endforeach
    </tbody></table></div>
    <h2 class="invoice-total">Total {{ brl($invoice->total_minor) }}</h2>
    @if($invoice->status==='paid')
        <div class="alert alert-success">Pagamento registrado em {{ $invoice->paid_at?->format('d/m/Y H:i') }}. {{ $invoice->type==='quote'?'A execução dos itens segue as condições do orçamento, pela equipe.':'Consulte a situação da ativação em Meus serviços.' }}</div>
    @elseif(in_array($invoice->status,['unpaid','overdue'])&&(!$invoice->expires_at||$invoice->expires_at->isFuture()))
        @if($invoice->expires_at)<p class="alert alert-warning">Reserva válida até {{ $invoice->expires_at->format('d/m/Y H:i') }} ({{ config('app.timezone') }}). Após esse prazo, não efetue pagamento; gere um novo pedido.</p>@endif
        <h3>Escolha como pagar</h3>
        @if($invoice->type!=='deposit')
            <form method="post" action="{{ route('invoices.wallet',$invoice) }}" class="wallet-payment-option">@csrf<button class="btn btn-primary">Usar saldo da conta ({{ brl(auth()->user()->balance_minor) }})</button></form>
        @endif
        <div class="payment-actions payment-gateway-actions">
            @foreach([
                'stripe' => ['label' => 'Cartão via Stripe', 'note' => 'Checkout seguro para cartões', 'logo' => 'stripe.svg', 'tone' => 'stripe'],
                'mercadopago' => ['label' => 'Mercado Pago', 'note' => 'Pix, cartão e saldo Mercado Pago', 'logo' => 'mercadopago.svg', 'tone' => 'mercadopago'],
                'efi' => ['label' => 'Pix via Efí Bank', 'note' => 'QR Code e copia-e-cola com confirmação automática', 'logo' => 'efi.svg', 'tone' => 'efi'],
            ] as $gateway=>$meta)
                @if(config('lagos.payments.'.$gateway.'.enabled'))
                    <form method="post" action="{{ route('invoices.gateway',$invoice) }}">@csrf<input type="hidden" name="gateway" value="{{ $gateway }}"><button class="payment-choice" type="submit"><span class="payment-choice-logo tone-{{ $meta['tone'] }}"><img src="{{ asset('assets/brands/payments/'.$meta['logo']) }}" alt="{{ $meta['label'] }}"></span><span class="payment-choice-copy"><strong>{{ $meta['label'] }}</strong><small>{{ $meta['note'] }}</small></span><span class="payment-choice-arrow">{!! panel_icon('chevron',17) !!}</span></button></form>
                @endif
            @endforeach
        </div>
        <p class="muted">Pagamento manual: solicite as instruções à equipe. A fatura só será quitada após conferência do recebimento do provedor.</p>
    @elseif(in_array($invoice->status,['unpaid','overdue'])&&$invoice->expires_at&&$invoice->expires_at->isPast())
        <div class="alert alert-warning">Reserva expirada. Não efetue pagamento. Cancele este pedido e escolha novamente na loja.</div>
    @endif
    @if($invoice->order_id&&in_array($invoice->status,['unpaid','overdue']))<form method="post" action="{{ route('invoices.cancel',$invoice) }}">@csrf<button class="btn btn-danger btn-sm">Cancelar pedido não pago</button></form>@endif
</div>
<a href="{{ route('invoices.index') }}">← Voltar às faturas</a>
@endsection
