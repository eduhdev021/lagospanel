@extends('layouts.panel')
@section('title','Recursos e cobertura das configurações')
@section('content')
@include('admin.configuration.nav')
<div class="settings-heading"><div><span class="eyebrow">O QUE FUNCIONA HOJE</span><h2>Configurações, sem promessas vazias.</h2><p>A central reúne recursos reais do LagosPanel. Ainda não existe equivalência completa com todas as opções do WHMCS.</p></div></div>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Área</th><th>Disponível nesta versão</th><th>Limitações e pendências</th></tr></thead><tbody>
@foreach([
 ['Identidade e acesso','Marca, homepage, SMTP, cadastro, login social, equipe e política de 2FA.','CAPTCHA configurável, campos personalizados de clientes e perfis multilíngues ainda não implementados.'],
 ['Pagamentos','Stripe e Mercado Pago, credenciais no ADM, modo teste/produção e confirmação no backend.','Sem homologação automática dos seus gateways. Outros meios exigem implementação específica.'],
 ['Faturamento','Identificação do emissor, PDF, reservas de pedidos e prazo de recarga.','BRL apenas. Sem motor tributário, múltiplas moedas, emissão fiscal ou editor de numeração fiscal.'],
 ['Automação','Antecedência de renovação, lembretes, suspensão por atraso e controles de ativação.','Exige cron e worker. Não inclui todos os agendamentos, regras de cancelamento e retenção do WHMCS.'],
 ['Suporte','SLA por prioridade, cota de anexos, respostas prontas, atribuição e atendimento por tickets.','Departamentos fixos Suporte/Financeiro; sem construtor de departamentos, regras de escalonamento ou importação de caixas de e-mail.'],
 ['Produtos e integrações','Catálogo, opções, descontos, conectores existentes, webhooks, provisionamento e Ollama.','Não é um marketplace compatível com extensões do WHMCS. Cada integração tem limites documentados.'],
 ['Domínios e afiliados','Não disponíveis como motores completos.','Registro/transferência/renovação por registrador, preços TLD e programa de afiliados ainda não implementados.'],
 ['Comunicação','Remetente/SMTP, lembretes existentes, avisos, base de conhecimento e respostas prontas de suporte.','Editor livre de todos os templates de e-mail e campanhas não implementados.'],
] as [$area,$available,$limits])<tr><td><strong>{{ $area }}</strong></td><td>{{ $available }}</td><td>{{ $limits }}</td></tr>@endforeach
</tbody></table></div></div>
@endsection
