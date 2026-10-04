@extends('layouts.panel')
@section('title', 'Subcontas e contatos')
@section('content')
<div class="card">
    <h3>Adicionar contato ou subconta</h3>
    <p class="muted">Autorize membros da sua equipe financeira ou técnica a visualizar faturas, gerenciar domínios ou abrir chamados em nome da sua conta.</p>
    <form method="post" action="{{ route('subaccounts.store') }}">
        @csrf
        <div class="form-grid">
            <div class="field"><label>Nome do contato</label><input type="text" name="name" required maxlength="120"></div>
            <div class="field"><label>E-mail do contato</label><input type="email" name="email" required maxlength="190"></div>
        </div>
        <div class="field">
            <label>Permissões delegadas</label>
            @foreach($permissions as $key => $label)
                <label class="check-line"><input type="checkbox" name="permissions[]" value="{{ $key }}"> {{ $label }}</label>
            @endforeach
        </div>
        <label class="check-line"><input type="checkbox" name="receive_billing_emails" value="1" checked> Receber avisos financeiros</label>
        <div class="field"><label>Sua senha atual para confirmar</label><input type="password" name="password" required autocomplete="current-password"></div>
        @if(auth()->user()->totp_secret)<div class="field"><label>Código 2FA</label><input type="text" name="code" maxlength="6" required></div>@endif
        <button class="btn btn-primary btn-sm">Salvar subconta</button>
    </form>
</div>

<div class="card">
    <h3>Contatos autorizados na sua conta</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nome</th><th>E-mail</th><th>Permissões</th><th>Ações</th></tr></thead>
            <tbody>
                @forelse($contacts as $contact)
                <tr>
                    <td>{{ $contact->name }}</td>
                    <td>{{ $contact->email }}</td>
                    <td>{{ implode(', ', $contact->permissions) }}</td>
                    <td>
                        <form method="post" action="{{ route('subaccounts.destroy', $contact) }}">
                            @csrf
                            <button class="btn btn-danger btn-sm">Remover</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4">Nenhuma subconta cadastrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($delegated->count())
<div class="card">
    <h3>Contas que delegaram acesso a você</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Titular</th><th>E-mail</th><th>Permissões concedidas</th></tr></thead>
            <tbody>
                @foreach($delegated as $d)
                <tr>
                    <td>{{ $d->owner?->name }}</td>
                    <td>{{ $d->owner?->email }}</td>
                    <td>{{ implode(', ', $d->permissions) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
