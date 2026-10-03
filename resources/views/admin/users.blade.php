@extends('layouts.panel')
@section('title', 'Clientes')
@section('content')
<div class="card"><div class="table-wrap"><table><thead><tr><th>Cliente</th><th>E-mail</th><th>Acesso</th><th>Saldo</th></tr></thead><tbody>@foreach($users as $u)<tr><td>#{{ $u->id }} — {{ $u->name }}</td><td>{{ $u->email }}</td><td>{{ $u->is_admin?'Administrador':'Cliente' }} · {{ $u->email_verified_at?'Confirmado':'Pendente' }}</td><td>{{ brl($u->balance_minor) }}</td></tr>@endforeach</tbody></table></div></div>{{ $users->links('layouts.pagination') }}
@endsection
