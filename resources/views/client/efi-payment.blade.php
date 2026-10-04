@extends('layouts.panel')
@section('title', 'Pagar com Pix')
@section('content')
<div class="gw-panel efi-pix-page" style="--gw:#f97316">
    <div class="efi-pix-brand"><span class="payment-choice-logo tone-efi"><img src="{{ asset('assets/brands/payments/efi.svg') }}" alt="Efí Bank"></span><div><span class="eyebrow">PAGAMENTO SEGURO</span><h2>Pix via Efí Bank</h2></div></div>
    <p class="muted">Fatura #{{ $invoice->id }} · confirme o pagamento no seu banco</p>
    <div class="gw-amount">{{ brl($charge->amount_minor) }}</div>
    <div class="efi-pix-state"><span class="badge badge-pending"><i class="badge-dot"></i>Aguardando confirmação</span><small>A confirmação acontece automaticamente após o webhook da Efí.</small></div>
    <div class="gw-qr" data-pix-qr></div>
    <div class="gw-instructions">
        <strong>Pix copia e cola</strong>
        <p>Abra o app do seu banco, escolha Pix e cole o código abaixo.</p>
        <textarea class="pix-code" data-pix-code readonly rows="4" aria-label="Código Pix copia e cola">{{ $charge->pix_copia_e_cola }}</textarea>
        <button type="button" class="btn btn-primary btn-block" data-copy="{{ $charge->pix_copia_e_cola }}" data-copied="Código Pix copiado">Copiar código Pix</button>
    </div>
    <div class="efi-pix-meta"><span>Expira em</span><strong>{{ $charge->expires_at?->format('d/m/Y H:i') ?? 'conforme a fatura' }}</strong><span>Referência</span><code>{{ $charge->reference }}</code></div>
    <p class="muted efi-pix-note">Não feche sua conta após pagar. Você pode voltar às faturas; o status será atualizado quando a Efí confirmar o recebimento.</p>
    <a class="btn btn-ghost" href="{{ route('invoices.show', $invoice) }}">Voltar para a fatura</a>
</div>
<script src="{{ panel_asset('assets/qrcode-generator.js') }}" defer></script>
<script src="{{ panel_asset('assets/efi-payment.js') }}" defer></script>
@endsection
