@extends('layouts.panel')
@section('title','Contas Pterodactyl')
@section('content')
<div class="card"><h2>{{ $connector->name }}</h2><p>Vincule uma conta de cliente já existente no Pterodactyl. O e-mail deve corresponder ao cliente LagosPanel; contas com privilégio administrador são recusadas. Não criamos usuários remotos nem compartilhamos a Application Key. O cliente entra no Pterodactyl com suas próprias credenciais.</p><p class="muted">Vínculos não podem ser sobrescritos por esta tela. Confira os IDs antes de salvar. A validação remota ocorre no provisionamento, não ao salvar.</p><a href="{{ route('admin.users') }}">Consultar IDs de clientes</a></div>
@if(auth()->user()->hasPermission('integrations.manage'))
<div class="card"><form method="post" action="{{ route('admin.connectors.accounts.create',$connector) }}">@csrf<div class="form-grid"><div class="field"><label>ID do cliente LagosPanel</label><input name="user_id" type="number" min="1" required></div><div class="field"><label>ID do usuário Pterodactyl</label><input name="remote_user_id" type="number" min="1" max="2147483647" required></div></div><label><input type="checkbox" name="ack" value="1" required> Conferi que ambas as contas pertencem ao mesmo cliente.</label><br><button class="btn btn-primary">Vincular conta</button></form></div>
@endif
<div class="card"><div class="table-wrap"><table><thead><tr><th>Cliente local</th><th>ID Pterodactyl</th></tr></thead><tbody>@foreach($accounts as $a)<tr><td>#{{ $a->user_id }} — {{ $a->user->name }} · {{ $a->user->email }}</td><td>{{ $a->remote_user_id }}</td></tr>@endforeach</tbody></table></div></div>{{ $accounts->links('layouts.pagination') }}
@endsection
