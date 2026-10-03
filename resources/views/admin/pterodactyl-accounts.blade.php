@extends('layouts.panel')
@section('title','Contas Pterodactyl')
@section('content')
<div class="card"><h2>{{ $connector->name }}</h2><p>Vincule uma conta de cliente já existente no Pterodactyl. O e-mail deve corresponder ao cliente LagosPanel; contas com privilégio administrador são recusadas. Você também pode criar uma conta remota por esta tela. Nunca compartilhamos a Application Key ou a senha do LagosPanel. O cliente entra no Pterodactyl com suas próprias credenciais.</p><p class="muted">Vínculos não podem ser sobrescritos por esta tela. Confira os IDs antes de salvar. A validação remota ocorre no provisionamento, não ao salvar.</p><a href="{{ route('admin.users') }}">Consultar IDs de clientes</a></div>
@if(auth()->user()->hasPermission('integrations.manage'))
<div class="card"><h2>Criar conta no Pterodactyl</h2><p>O cliente precisa ter e-mail verificado. Exige integração ativa, chamadas nativas liberadas e Application Key com leitura e criação de usuários.</p><p class="muted">O Pterodactyl gera o acesso inicial e envia a orientação de senha pelo próprio SMTP. O LagosPanel não define nem exibe essa senha. Conta com e-mail já existente deve ser vinculada manualmente, não duplicada.</p>
<form method="post" action="{{ route('admin.connectors.accounts.provision',$connector) }}">
@csrf
<div class="form-grid"><div class="field"><label>ID do cliente LagosPanel</label><input name="user_id" type="number" min="1" required></div><div class="field"><label>Nome</label><input name="first_name" maxlength="64" required></div><div class="field"><label>Sobrenome</label><input name="last_name" maxlength="64" required></div></div>
<label><input name="ack" type="checkbox" value="1" required> Confirmo a criação e o envio do nome/e-mail deste cliente ao Pterodactyl.</label><br><button class="btn btn-primary">Criar e vincular conta remota</button>
</form></div>

<div class="card"><form method="post" action="{{ route('admin.connectors.accounts.create',$connector) }}">@csrf<div class="form-grid"><div class="field"><label>ID do cliente LagosPanel</label><input name="user_id" type="number" min="1" required></div><div class="field"><label>ID do usuário Pterodactyl</label><input name="remote_user_id" type="number" min="1" max="2147483647" required></div></div><label><input type="checkbox" name="ack" value="1" required> Conferi que ambas as contas pertencem ao mesmo cliente.</label><br><button class="btn btn-primary">Vincular conta</button></form></div>
@endif
<div class="card"><div class="table-wrap"><table><thead><tr><th>Cliente local</th><th>ID Pterodactyl</th></tr></thead><tbody>@foreach($accounts as $a)<tr><td>#{{ $a->user_id }} — {{ $a->user->name }} · {{ $a->user->email }}</td><td>{{ $a->remote_user_id }}</td></tr>@endforeach</tbody></table></div></div>{{ $accounts->links('layouts.pagination') }}
<div class="card"><h2>Solicitações de criação</h2><p class="muted">Após envio, a conferência é somente leitura: não recria contas nem reenvia convites. Operações interrompidas podem ser conferidas após dois minutos. Se necessário, a equipe deve corrigir divergências no provedor.</p><div class="table-wrap"><table><thead><tr><th>Cliente</th><th>Usuário gerado</th><th>Situação</th><th>Ação</th></tr></thead><tbody>
@forelse($requests as $attempt)
<tr><td>#{{ $attempt->user_id }} — {{ $attempt->user->name }}</td><td>{{ $attempt->username }}</td><td>{{ ['done'=>'Vinculado','processing'=>'Em andamento','review'=>'Revisar'][$attempt->status] ?? 'Revisar' }}{{ $attempt->sent_at ? ' · envio registrado' : ' · sem envio registrado' }}</td><td>
@if(auth()->user()->hasPermission('integrations.manage') && $attempt->status !== 'done')
<form method="post" action="{{ route('admin.connectors.accounts.inspect',[$connector,$attempt]) }}">
@csrf
<button class="btn">Conferir resultado</button></form>
@if(!$attempt->sent_at && $attempt->status === 'review')
<form method="post" action="{{ route('admin.connectors.accounts.provision',$connector) }}">
@csrf
<input type="hidden" name="user_id" value="{{ $attempt->user_id }}"><input type="hidden" name="first_name" value="{{ $attempt->first_name }}"><input type="hidden" name="last_name" value="{{ $attempt->last_name }}"><label><input type="checkbox" name="ack" value="1" required> Autorizar nova tentativa ainda não enviada</label><button class="btn">Tentar criação</button></form>
@endif
@endif
</td></tr>
@empty
<tr><td colspan="4">Nenhuma criação solicitada.</td></tr>
@endforelse
</tbody></table></div></div>{{ $requests->links('layouts.pagination') }}
@endsection
