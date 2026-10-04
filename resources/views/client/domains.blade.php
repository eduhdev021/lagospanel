@extends('layouts.panel')
@section('title', 'Meus domínios')
@section('content')
<div class="card">
    <h3>Pesquisar e registrar domínio</h3>
    <p class="muted">Consulte a disponibilidade ou transfira seu domínio existente para gerenciar nameservers, bloqueio e renovação em um só lugar.</p>
    <form method="get" action="{{ route('domains.index') }}">
        <div class="form-grid">
            <div class="field">
                <label for="domain-search">Nome do domínio</label>
                <input id="domain-search" type="text" name="q" value="{{ request('q') }}" placeholder="meusite.com.br" required maxlength="190">
            </div>
        </div>
        <button class="btn btn-primary btn-sm">Verificar disponibilidade</button>
    </form>

    @if($lookup)
        <div class="card" style="margin-top:1rem">
            @if(!$lookup['valid'] || !$lookup['tld'])
                <p><strong>{{ $lookup['domain'] ?: request('q') }}</strong> — Extensão (TLD) não encontrada no catálogo ativo ou formato inválido.</p>
            @elseif($lookup['available'])
                <p><strong>{{ $lookup['domain'] }}</strong> está <span class="badge badge-active">Disponível</span> ({{ brl($lookup['tld']->register_minor) }}/ano · renovação {{ brl($lookup['tld']->renew_minor) }}/ano).</p>
            @else
                <p><strong>{{ $lookup['domain'] }}</strong> já consta registrado. Caso seja titular, você pode solicitar a transferência ({{ brl($lookup['tld']->transfer_minor) }}).</p>
            @endif
        </div>
    @endif
</div>

@if($tlds->count())
<div class="card">
    <h3>Contratar ou transferir domínio</h3>
    <form method="post" action="{{ route('domains.order') }}">
        @csrf
        <div class="form-grid">
            <div class="field">
                <label>Domínio completo</label>
                <input type="text" name="domain" value="{{ $lookup['domain'] ?? old('domain') }}" placeholder="exemplo.com.br" required maxlength="190">
            </div>
            <div class="field">
                <label>Operação</label>
                <select name="operation_type">
                    <option value="register">Registrar novo domínio</option>
                    <option value="transfer">Transferir domínio existente</option>
                </select>
            </div>
            <div class="field">
                <label>Período (anos)</label>
                <input type="number" name="years" value="1" min="1" max="10" required>
            </div>
            <div class="field">
                <label>Código EPP / Auth Code (somente transferência)</label>
                <input type="text" name="epp_code" maxlength="120" placeholder="Obrigatório para transferência">
            </div>
            <div class="field">
                <label>Nameserver 1</label>
                <input type="text" name="ns1" value="{{ old('ns1', 'ns1.lagos.local') }}" required maxlength="190">
            </div>
            <div class="field">
                <label>Nameserver 2</label>
                <input type="text" name="ns2" value="{{ old('ns2', 'ns2.lagos.local') }}" required maxlength="190">
            </div>
        </div>
        <button class="btn btn-primary btn-sm">Continuar contratação</button>
    </form>

    <div class="table-wrap" style="margin-top:1rem">
        <table>
            <thead><tr><th>Extensão (TLD)</th><th>Registro (1 ano)</th><th>Transferência</th><th>Renovação</th><th>Registrador</th></tr></thead>
            <tbody>
                @foreach($tlds as $t)
                <tr>
                    <td><strong>{{ $t->tld }}</strong></td>
                    <td>{{ brl($t->register_minor) }}</td>
                    <td>{{ brl($t->transfer_minor) }}</td>
                    <td>{{ brl($t->renew_minor) }}</td>
                    <td>{{ strtoupper($t->registrar) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@if($revealedEpp)
<div class="alert alert-warning">
    Código EPP / Auth Code do domínio <strong>{{ $revealedEpp['domain'] }}</strong>: <code>{{ $revealedEpp['code'] }}</code>
</div>
@endif

<div class="card">
    <h3>Domínios da sua conta</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Domínio</th><th>Estado</th><th>Expiração</th><th>Renovação</th><th>Bloqueio</th></tr></thead>
            <tbody>
                @forelse($domains as $d)
                <tr>
                    <td><strong>{{ $d->domain }}</strong></td>
                    <td><span class="badge badge-{{ $d->status }}">{{ status_label($d->status) }}</span></td>
                    <td>{{ $d->expires_at ? $d->expires_at->format('d/m/Y') : 'Aguardando pagamento' }}</td>
                    <td>{{ brl($d->renew_minor) }}/ano</td>
                    <td>{{ $d->transfer_lock ? 'Bloqueado' : 'Desbloqueado' }}</td>
                </tr>
                @empty
                <tr><td colspan="5">Nenhum domínio registrado até o momento.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@foreach($domains as $d)
@if($d->status !== 'cancelled')
<details class="card">
    <summary>Gerenciar DNS, EPP e renovação — {{ $d->domain }}</summary>
    <form method="post" action="{{ route('domains.nameservers', $d) }}">
        @csrf
        <div class="form-grid">
            <div class="field"><label>Nameserver 1</label><input type="text" name="ns1" value="{{ $d->nameservers[0] ?? 'ns1.lagos.local' }}" required></div>
            <div class="field"><label>Nameserver 2</label><input type="text" name="ns2" value="{{ $d->nameservers[1] ?? 'ns2.lagos.local' }}" required></div>
            <div class="field"><label>Nameserver 3 (opcional)</label><input type="text" name="ns3" value="{{ $d->nameservers[2] ?? '' }}"></div>
            <div class="field"><label>Nameserver 4 (opcional)</label><input type="text" name="ns4" value="{{ $d->nameservers[3] ?? '' }}"></div>
        </div>
        <button class="btn btn-primary btn-sm">Salvar nameservers</button>
    </form>

    <div class="form-grid" style="margin-top:1rem">
        <form method="post" action="{{ route('domains.lock', $d) }}">
            @csrf
            <button class="btn btn-ghost btn-sm">{{ $d->transfer_lock ? 'Desbloquear transferência' : 'Ativar bloqueio de transferência' }}</button>
        </form>

        @if($d->status === 'active')
        <form method="post" action="{{ route('domains.renew', $d) }}">
            @csrf
            <input type="hidden" name="years" value="1">
            <button class="btn btn-ghost btn-sm">Renovar por +1 ano ({{ brl($d->renew_minor) }})</button>
        </form>
        @endif
    </div>

    <form method="post" action="{{ route('domains.epp', $d) }}" style="margin-top:1rem">
        @csrf
        <div class="field"><label>Sua senha atual para revelar o código EPP</label><input type="password" name="password" required autocomplete="current-password"></div>
        @if(auth()->user()->totp_secret)<div class="field"><label>Código 2FA</label><input type="text" name="code" maxlength="6" required></div>@endif
        <button class="btn btn-ghost btn-sm">Ver código EPP / Auth Code</button>
    </form>
</details>
@endif
@endforeach

{{ $domains->links('layouts.pagination') }}
@endsection
