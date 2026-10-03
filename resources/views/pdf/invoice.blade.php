<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><style>
@page { margin: 38px; } body { font-family: 'DejaVu Sans', sans-serif; color: #172a46; font-size: 10px; line-height: 1.5; }
h1 { font-size: 25px; margin: 0; } h2 { font-size: 16px; } .muted { color: #52637b; } .header { border-bottom: 3px solid #316bf3; padding-bottom: 16px; } table { width: 100%; border-collapse: collapse; margin: 20px 0; table-layout: fixed; } th, td { text-align: left; padding: 9px; border-bottom: 1px solid #dae1ed; word-wrap: break-word; } th { background: #edf2fb; } .total { text-align: right; font-size: 18px; } .note { padding-top: 20px; border-top: 1px solid #dae1ed; } pre { white-space: pre-wrap; font-family: inherit; }
</style></head><body>
<div class="header"><h1>{{ config('documents.issuer_name') }}</h1><p class="muted">{{ config('documents.issuer_details') }}</p><h2>Fatura #{{ $invoice->id }} — {{ status_label($invoice->status) }}</h2></div>
<p><strong>Cliente:</strong> {{ $invoice->user->name }}<br>{{ $invoice->user->email }}</p>
<p>Emissão: {{ $invoice->created_at->format('d/m/Y') }} · Vencimento: {{ $invoice->due_date->format('d/m/Y') }}<br>Moeda: BRL · Tipo: {{ $invoice->type === 'deposit' ? 'Adição de saldo' : 'Serviços' }}</p>
@if($invoice->paid_at)<p>Pagamento registrado: {{ $invoice->paid_at->format('d/m/Y H:i') }}</p>@endif
<table><thead><tr><th style="width:52%">Descrição</th><th style="width:12%">Qtd.</th><th style="width:18%">Unitário</th><th style="width:18%">Instalação/un.</th></tr></thead><tbody>
@foreach($invoice->snapshot ?? [] as $item)
<tr><td>{{ $item['name'] ?? 'Item' }}
@foreach($item['configuration'] ?? [] as $c)<br><small class="muted">{{ $c['name'] ?? '' }}: {{ $c['label'] ?? '' }}</small>@endforeach
@if(!empty($item['discount_minor']))<br><small>Desconto inicial: {{ brl((int)$item['discount_minor']) }}</small>@endif
</td><td>{{ $item['quantity'] ?? 1 }}</td><td>{{ brl((int)($item['unit_minor'] ?? 0)) }}</td><td>{{ brl((int)($item['setup_minor'] ?? 0)) }}</td></tr>
@endforeach
</tbody></table>
<p class="total"><strong>Total: {{ brl($invoice->total_minor) }}</strong></p>
<p class="note"><strong>Documento de cobrança — não é nota fiscal.</strong><br>Este PDF não confirma recebimento por si só. Consulte o estado atual e as instruções de pagamento no portal autenticado. Não pague faturas canceladas ou reservas expiradas.</p>
<p class="muted">Gerado em {{ $generatedAt->format('d/m/Y H:i') }} ({{ config('app.timezone') }}). Itens e valores do registro da fatura; identificação do cliente e do emissor conforme cadastro atual.</p>
</body></html>
