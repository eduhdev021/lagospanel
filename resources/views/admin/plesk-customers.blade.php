@extends('layouts.panel')
@section('title', 'Clientes Plesk')
@section('content')
<div class="card"><h3>{{ $connector->name }}</h3><p>Contas isoladas preparadas após pagamento. A senha temporária é apagada após confirmação. Nenhuma senha ou sessão remota é exibida à equipe.</p><p>Em revisão, confira o login e o identificador externo no Plesk. Em Operações, use “Retomar preparação de conta” no pedido correspondente. Após um envio, o painel apenas consulta; não repete a criação. Não apague este registro para forçar um reenvio.</p>
<div class="table-wrap"><table><thead><tr><th>Cliente</th><th>Login / identificador externo</th><th>Estado</th><th>ID remoto</th><th>Enviado em</th></tr></thead><tbody>
@forelse($requests as $request)
<tr><td>#{{ $request->user_id }} · {{ $request->user->name }}<br>{{ $request->email }}</td><td style="overflow-wrap:anywhere">{{ $request->login }}<br>{{ $request->external_id }}</td><td>{{ ['done'=>'Confirmado','review'=>'Revisão necessária','processing'=>'Em processamento'][$request->status]??$request->status }}</td><td>{{ $request->remote_id??'Não confirmado' }}</td><td>{{ $request->sent_at?->format('d/m/Y H:i:s')??'Não enviado' }}</td></tr>
@empty<tr><td colspan="5">Nenhum cliente automático preparado nesta integração.</td></tr>@endforelse
</tbody></table></div>{{ $requests->links('layouts.pagination') }}</div>
@endsection
