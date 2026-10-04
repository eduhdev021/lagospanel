@extends('layouts.panel')
@section('title', 'Domínios e TLDs')
@section('content')
@if(auth()->user()->hasPermission('products.manage'))
<div class="card">
    <h3>Cadastrar ou atualizar extensão TLD</h3>
    <form method="post" action="{{ route('admin.domains.tlds.save') }}">
        @csrf
        <div class="form-grid">
            <div class="field"><label>Extensão (ex.: .com.br)</label><input type="text" name="tld" placeholder=".com.br" required maxlength="64"></div>
            <div class="field"><label>Preço de registro (R$)</label><input type="text" name="register_price" placeholder="49,90" required></div>
            <div class="field"><label>Preço de transferência (R$)</label><input type="text" name="transfer_price" placeholder="49,90" required></div>
            <div class="field"><label>Preço de renovação (R$)</label><input type="text" name="renew_price" placeholder="54,90" required></div>
            <div class="field">
                <label>Registrador</label>
                <select name="registrar">
                    <option value="manual">Manual / Interno</option>
                    <option value="registrobr">Registro.br (EPP)</option>
                    <option value="enom">Enom</option>
                    <option value="namecheap">Namecheap</option>
                    <option value="resellerclub">ResellerClub</option>
                </select>
            </div>
        </div>
        <label class="check-line"><input type="checkbox" name="active" value="1" checked> Disponível para clientes</label>
        <button class="btn btn-primary btn-sm">Salvar TLD</button>
    </form>
</div>
@endif

<div class="card">
    <h3>Catálogo de TLDs</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>TLD</th><th>Registro</th><th>Transferência</th><th>Renovação</th><th>Registrador</th><th>Ativo</th></tr></thead>
            <tbody>
                @forelse($tlds as $t)
                <tr>
                    <td><strong>{{ $t->tld }}</strong></td>
                    <td>{{ brl($t->register_minor) }}</td>
                    <td>{{ brl($t->transfer_minor) }}</td>
                    <td>{{ brl($t->renew_minor) }}</td>
                    <td>{{ strtoupper($t->registrar) }}</td>
                    <td>{{ $t->active ? 'Sim' : 'Não' }}</td>
                </tr>
                @empty
                <tr><td colspan="6">Nenhuma extensão TLD cadastrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3>Registros de domínios de clientes</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Domínio</th><th>Cliente</th><th>Operação</th><th>Estado</th><th>Expiração</th><th>Ação</th></tr></thead>
            <tbody>
                @forelse($domains as $d)
                <tr>
                    <td><strong>{{ $d->domain }}</strong></td>
                    <td>{{ $d->user?->email }}</td>
                    <td>{{ $d->operation_type }} ({{ $d->years }}a)</td>
                    <td><span class="badge badge-{{ $d->status }}">{{ status_label($d->status) }}</span></td>
                    <td>{{ $d->expires_at?->format('d/m/Y') ?? '—' }}</td>
                    <td>
                        @if(auth()->user()->hasPermission('products.manage'))
                        <form method="post" action="{{ route('admin.domains.status', $d) }}" style="display:flex;gap:0.5rem">
                            @csrf
                            <select name="status">
                                @foreach(['pending'=>'Pendente','active'=>'Ativo','expired'=>'Expirado','cancelled'=>'Cancelado'] as $st=>$lbl)
                                    <option value="{{ $st }}" @selected($d->status===$st)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-ghost btn-sm">Salvar</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6">Nenhum domínio contratado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $domains->links('layouts.pagination') }}
@endsection
