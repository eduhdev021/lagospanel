@extends('layouts.panel')
@section('title', 'Addons e Upgrades')
@section('content')
@if(auth()->user()->hasPermission('products.manage'))
<div class="card">
    <h3>Novo addon de serviço</h3>
    <p class="muted">Adicionais (IP dedicado, backup diário, suporte VIP, licenças extras) que clientes podem contratar para serviços ativos.</p>
    <form method="post" action="{{ route('admin.addons.save') }}">
        @csrf
        <div class="form-grid">
            <div class="field"><label>Nome do addon</label><input type="text" name="name" required maxlength="180"></div>
            <div class="field"><label>Preço recorrente (R$)</label><input type="text" name="price" placeholder="15,00" required></div>
            <div class="field"><label>Taxa de instalação (R$)</label><input type="text" name="setup" value="0.00"></div>
            <div class="field">
                <label>Ciclo</label>
                <select name="cycle">
                    @foreach(\App\Support\Cycle::LABELS as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="field"><label>Descrição</label><textarea name="description" maxlength="1000"></textarea></div>
        <label class="check-line"><input type="checkbox" name="active" value="1" checked> Disponível para contratação</label>
        <button class="btn btn-primary btn-sm">Salvar addon</button>
    </form>
</div>
@endif

<div class="card">
    <h3>Catálogo de addons</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nome</th><th>Preço</th><th>Instalação</th><th>Ciclo</th><th>Ativo</th></tr></thead>
            <tbody>
                @forelse($addons as $a)
                <tr>
                    <td><strong>{{ $a->name }}</strong></td>
                    <td>{{ brl($a->price_minor) }}</td>
                    <td>{{ brl($a->setup_minor) }}</td>
                    <td>{{ \App\Support\Cycle::LABELS[$a->cycle] ?? $a->cycle }}</td>
                    <td>{{ $a->active ? 'Sim' : 'Não' }}</td>
                </tr>
                @empty
                <tr><td colspan="5">Nenhum addon cadastrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3>Histórico de upgrades e downgrades (Prorrata)</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Serviço</th><th>Cliente</th><th>De</th><th>Para</th><th>Diferença (Prorrata)</th><th>Estado</th></tr></thead>
            <tbody>
                @forelse($upgrades as $u)
                <tr>
                    <td>#{{ $u->service_id }}</td>
                    <td>{{ $u->user?->email }}</td>
                    <td>{{ $u->fromProduct?->name ?? '—' }}</td>
                    <td>{{ $u->toProduct?->name ?? '—' }}</td>
                    <td>{{ brl($u->delta_minor) }}</td>
                    <td>{{ $u->status }}</td>
                </tr>
                @empty
                <tr><td colspan="6">Nenhum upgrade ou downgrade registrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $upgrades->links('layouts.pagination') }}
@endsection
