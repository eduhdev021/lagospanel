@extends('layouts.panel')
@section('title','Configurações da administração')
@section('content')
<div class="card"><h2>Configurações</h2><p>Escolha uma seção. Cada formulário salva somente as opções daquela seção; faturamento, atendimento e serviços continuam nos respectivos módulos.</p></div>
<div class="form-grid">
@foreach([
 ['admin.settings.general','Geral e identidade','Nome, endereço do painel, logo, contato e abertura de cadastros.'],
 ['admin.settings.email','E-mail e notificações','SMTP, remetente, senha do transportador e teste de envio.'],
 ['admin.connectors','Integrações e servidores','Contas dos provedores, chaves e ativação por integração.'],
 ['admin.connectors.ai','Assistente e pesquisa','Ollama, modelos, chave e pesquisa web do assistente.'],
 ['admin.webhooks.index','Webhooks de saída','Destinos, assinaturas, histórico e retentativas.'],
 ['admin.products','Catálogo e planos','Produtos, preços, opções e parâmetros de provisionamento.'],
 ['admin.team.index','Equipe e permissões','Funções e acessos dos operadores, sem compartilhar senha.'],
 ['admin.settings.environment','Ambiente e recursos','Estado de pagamentos, provisionamento, fila e requisitos do servidor.']
] as [$route,$title,$description])
@if(auth()->user()->hasPermission(\App\Support\AdminPermissions::forRoute($route)))<div class="card"><h3>{{ $title }}</h3><p>{{ $description }}</p><a class="btn btn-primary btn-sm" href="{{ route($route) }}">Configurar</a></div>@endif
@endforeach
@if(auth()->user()->is_admin)<div class="card"><h3>Segurança do ADM</h3><p>Defina se o autenticador é obrigatório para a equipe. Login, senha e permissões continuam exigidos.</p><a class="btn btn-primary btn-sm" href="{{ route('admin.settings.security') }}">Configurar segurança</a></div><div class="card"><h3>Atualizações do painel</h3><p>Consultar o GitHub oficial, aprovar uma versão e acompanhar a execução com backup.</p><a class="btn btn-primary btn-sm" href="{{ route('admin.settings.updates') }}">Abrir atualizador</a></div>@endif
</div>
@endsection
