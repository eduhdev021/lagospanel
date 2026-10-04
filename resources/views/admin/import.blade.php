@extends('layouts.panel')
@section('title', 'Importação WHMCS / Paymenter')
@section('content')
<div class="card">
    <h3>Importador de migração (WHMCS & Paymenter)</h3>
    <p class="muted">Importe clientes, produtos, serviços e faturas a partir de um pacote JSON exportado do WHMCS ou Paymenter. A operação é idempotente por ID externo e marca as contas importadas para redefinição obrigatória de senha.</p>
    <form method="post" action="{{ route('admin.import.run') }}">
        @csrf
        <div class="field">
            <label>Sistema de origem</label>
            <select name="source">
                <option value="whmcs">WHMCS</option>
                <option value="paymenter">Paymenter</option>
            </select>
        </div>
        <div class="field">
            <label>Pacote JSON de migração</label>
            <textarea name="payload_json" rows="10" required placeholder='{"clients":[{"id":"101","name":"Cliente Exemplo","email":"cliente@exemplo.com","tax_id":"123.456.789-00"}],"products":[{"id":"1","name":"Hospedagem Turbo","price_minor":2990,"cycle":"monthly"}],"services":[{"client_id":"101","product_id":"1","status":"active"}],"invoices":[{"client_id":"101","total_minor":2990,"status":"paid"}]}'>{{ old('payload_json') }}</textarea>
        </div>
        <div class="field"><label>Sua senha atual do LagosPanel</label><input type="password" name="password" required autocomplete="current-password"></div>
        @if(auth()->user()->totp_secret)<div class="field"><label>Código 2FA</label><input type="text" name="code" maxlength="6" required></div>@endif
        <label class="check-line"><input type="checkbox" name="ack" value="1" required> Confirmo que revisei o arquivo JSON e que contas importadas exigirão redefinição de senha no primeiro acesso.</label>
        <button class="btn btn-primary">Executar importação</button>
    </form>
</div>
@endsection
