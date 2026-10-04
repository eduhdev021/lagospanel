@extends('layouts.panel')
@section('title','Configurações')
@section('content')
<div class="settings-center" data-settings-center>
<div class="settings-breadcrumb">Administração <span>/</span> Sistema</div>
<div class="settings-heading"><div><span class="eyebrow">DO SEU JEITO</span><h2>Configurações do sistema<span class="heading-dot">.</span></h2><p>Tudo o que sua operação precisa, organizado em um só lugar.</p></div><a class="btn btn-ghost" href="{{ route('home') }}" target="_blank" rel="noopener">{!! panel_icon('globe',18) !!} Ver meu site {!! panel_icon('chevron',15) !!}</a></div>
<div class="settings-searchbar"><label for="settings-search">{!! panel_icon('search',21) !!}<input id="settings-search" type="search" placeholder="O que você quer configurar? Ex.: e-mail, login, planos..." autocomplete="off"></label><span>Encontre sem complicação</span></div>
<div class="settings-workspace"><nav class="settings-categories" aria-label="Categorias de configurações">
@foreach(['all'=>['settings','Todas as configurações'],'business'=>['globe','Seu negócio'],'customers'=>['user','Clientes e acesso'],'commerce'=>['store','Produtos e vendas'],'support'=>['ticket','Atendimento'],'integrations'=>['api','Integrações'],'system'=>['shield','Sistema e segurança']] as $key=>[$icon,$label])<button type="button" data-settings-filter="{{ $key }}" class="{{ $key==='all'?'is-active':'' }}" aria-pressed="{{ $key==='all'?'true':'false' }}">{!! panel_icon($icon,19) !!}<span>{{ $label }}</span>@if($key==='all')<span class="filter-count" data-settings-count></span>@endif</button>@endforeach
<div class="settings-help"><span class="settings-mini-icon">{!! panel_icon('book',22) !!}</span><strong>Uma configuração de cada vez.</strong><p>Escolha uma seção para ajustar as opções. As permissões da sua equipe continuam valendo.</p></div></nav>
<div class="settings-results"><div class="settings-results-heading"><h3>Explore as configurações</h3><span data-settings-result aria-live="polite"></span></div><div class="settings-tile-grid">
@foreach([
 ['admin.settings.general','globe','business','Geral, marca e rodapé','Nome, logo, contato e os textos públicos do rodapé.','violet'],
 ['admin.settings.homepage','home','business','Página inicial','Apresentação, chamada principal e vitrine de planos.','blue'],
 ['admin.settings.email','mail','business','E-mail e notificações','Remetente, servidor SMTP e envio de teste.','amber'],
 ['admin.settings.social','key','customers','Login social','Google, GitHub, Microsoft, Facebook e X / Twitter.','blue'],
 ['admin.team.index','user','customers','Equipe e permissões','Pessoas, funções e níveis de acesso ao painel.','violet'],
 ['admin.products','store','commerce','Produtos e planos','Seu catálogo, preços e opções de contratação.','green'],
 ['admin.coupons','card','commerce','Cupons e descontos','Promoções e condições para suas vendas.','amber'],
 ['admin.connectors','server','integrations','Servidores e provedores','Conecte a infraestrutura aos seus serviços.','blue'],
 ['admin.connectors.ai','zap','integrations','Assistente com IA','Ollama, modelos e pesquisa para o atendimento.','violet'],
 ['admin.webhooks.index','api','integrations','Webhooks','Eventos, assinaturas e integrações de saída.','green'],
 ['admin.settings.security','shield','system','Segurança do ADM','Política de acesso e exigência de duas etapas.','green'],
 ['admin.settings.updates','refresh','system','Atualizações','Versões, verificações e atualização do painel.','violet'],
 ['admin.settings.environment','monitor','system','Ambiente e recursos','Requisitos e estado dos recursos do servidor.','blue'],
 ['admin.tickets.templates','ticket','support','Respostas prontas','Textos de apoio para agilizar o atendimento humano.','blue'],
 ['admin.knowledge','book','support','Base de conhecimento','Artigos de ajuda e orientações para seus clientes.','green'],
 ['admin.bulletins.index','globe','business','Avisos e status','Comunicados e informações públicas da operação.','blue'],
 ['admin.audit','book','system','Auditoria','Histórico de ações administrativas.','amber']
] as [$route,$icon,$category,$title,$description,$color])
@if(auth()->user()->hasPermission(\App\Support\AdminPermissions::forRoute($route)) && (!in_array($route,['admin.settings.security','admin.settings.updates','admin.settings.social'])||auth()->user()->is_admin))
<a class="settings-tile" href="{{ route($route) }}" data-settings-item data-category="{{ $category }}"><span class="settings-tile-icon tone-{{ $color }}">{!! panel_icon($icon,25) !!}</span><div><h4>{{ $title }}</h4><p>{{ $description }}</p></div><span class="settings-tile-arrow">{!! panel_icon('chevron',17) !!}</span></a>
@endif
@endforeach
@if(auth()->user()->is_admin)
@foreach(\App\Support\OperationalSettings::SECTIONS as $id=>$section)
<a class="settings-tile" href="{{ route('admin.settings.operation',$id) }}" data-settings-item data-category="{{ in_array($id,['billing','payments'])?'commerce':($id==='support'?'support':'system') }}"><span class="settings-tile-icon tone-{{ $id==='payments'?'green':'violet' }}">{!! panel_icon($section['icon'],25) !!}</span><div><h4>{{ $section['title'] }}</h4><p>{{ ['billing'=>'Emissor da fatura, reservas e vencimentos.','payments'=>'Stripe e Mercado Pago configuráveis no ADM.','automation'=>'Renovações, lembretes e suspensão por atraso.','support'=>'Prazos por prioridade e cota de anexos.','resources'=>'Provisionamento, webhooks e limites de IA.'][$id] }}</p></div><span class="settings-tile-arrow">{!! panel_icon('chevron',17) !!}</span></a>
@endforeach
@endif
</div><div class="settings-empty" data-settings-empty hidden>{!! panel_icon('search',34) !!}<h3>Nenhuma configuração encontrada</h3><p>Tente outro termo ou escolha uma categoria diferente.</p><button class="btn btn-ghost" type="button" data-settings-reset>Limpar busca e filtros</button></div><p class="settings-bottom-note">{!! panel_icon('shield',15) !!} Você vê somente as configurações permitidas para sua conta.</p><p><a href="{{ route('admin.settings.coverage') }}">Consultar recursos disponíveis e limitações desta versão {!! panel_icon('chevron',14) !!}</a></p></div></div>
</div><script src="{{ panel_asset('assets/settings-center.js') }}" defer></script>
@endsection
