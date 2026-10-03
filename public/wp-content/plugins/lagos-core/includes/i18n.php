<?php
/**
 * LagosPanel Core — Internacionalização (PT-BR / EN)
 */
if (!defined('ABSPATH')) exit;

function lagos_langs() {
    return ['pt' => 'Português (BR)', 'en' => 'English'];
}

function lagos_i18n() {
    static $d = null;
    if ($d !== null) return $d;

    $d = [
    'pt' => [
        // Marca & navegação
        'brand_tag' => 'Lagos Soluções', 'nav_home' => 'Início', 'nav_store' => 'Loja',
        'nav_features' => 'Recursos', 'nav_pricing' => 'Preços', 'nav_login' => 'Entrar',
        'nav_register' => 'Criar conta',
        // Hero
        'hero_badge' => ' Uma companhia Lagos',
        'hero_title' => 'O painel de billing que o seu negócio merece',
        'hero_sub' => 'Clientes, serviços, faturas e suporte em um só lugar. Mais moderno que o WHMCS, mais completo que o Paymenter — e feito para o Brasil.',
        'hero_cta' => 'Criar minha conta', 'hero_cta2' => 'Explorar a loja',
        'stat1_v' => '+2.400', 'stat1_l' => 'clientes na plataforma',
        'stat2_v' => '99,9%', 'stat2_l' => 'de uptime médio',
        'stat3_v' => '24/7', 'stat3_l' => 'suporte humanizado',
        'stat4_v' => '<80ms', 'stat4_l' => 'resposta média da API',
        // Features
        'features_title' => 'Tudo que um painel moderno precisa',
        'features_sub' => 'Construído para empresas de hospedagem que querem crescer sem dor de cabeça.',
        'f1t' => 'Billing inteligente', 'f1d' => 'Faturas recorrentes, cobrança por Pix e cartão, lembretes automáticos e conciliação em tempo real.',
        'f2t' => 'Provisionamento automático', 'f2d' => 'Hospedagens, VPS e domínios criados instantaneamente após a confirmação do pagamento.',
        'f3t' => 'Central de suporte', 'f3d' => 'Tickets com departamentos, prioridades, respostas por e-mail e SLA configurável.',
        'f4t' => 'Multi-idioma nativo', 'f4d' => 'Português e inglês embutidos — com um clique o cliente escolhe o idioma.',
        'f5t' => 'Segurança em primeiro lugar', 'f5d' => '2FA, chaves de API por cliente, logs de auditoria e criptografia em repouso.',
        'f6t' => 'API & Webhooks', 'f6d' => 'REST API completa para integrar provisionadores, gateways e painéis externos.',
        // Preços
        'pricing_title' => 'Planos prontos para vender',
        'pricing_sub' => 'Produtos de demonstração já cadastrados — personalize tudo no admin.',
        'from' => 'a partir de', 'month' => '/mês', 'year' => '/ano', 'one_time' => '/único',
        'cta_title' => 'Pronto para evoluir seu negócio?',
        'cta_sub' => 'Crie sua conta agora e veja o LagosPanel em ação.',
        'cta_btn' => 'Começar agora', 'view_all' => 'Ver tudo',
        'featured_badge' => 'Mais vendido',
        // Footer
        'footer_tag' => 'O painel de nova geração para empresas de hospedagem. Um produto da companhia Lagos.',
        'footer_product' => 'Produto', 'footer_company' => 'Companhia', 'footer_legal' => 'Legal',
        'footer_about' => 'Sobre', 'footer_contact' => 'Contato',
        'footer_terms' => 'Termos de uso', 'footer_privacy' => 'Privacidade',
        'footer_rights' => 'Todos os direitos reservados.',
        // Autenticação
        'login_title' => 'Bem-vindo de volta', 'login_sub' => 'Acesse sua conta LagosPanel',
        'register_title' => 'Criar sua conta', 'register_sub' => 'Leva menos de um minuto.',
        'label_email' => 'E-mail', 'label_password' => 'Senha', 'label_name' => 'Nome completo',
        'label_confirm' => 'Confirmar senha', 'label_phone' => 'Telefone',
        'remember' => 'Manter conectado', 'forgot' => 'Esqueceu a senha?',
        'btn_login' => 'Entrar', 'btn_register' => 'Criar conta',
        'no_account' => 'Ainda não tem conta?', 'have_account' => 'Já tem uma conta?',
        'terms' => 'Ao continuar você concorda com os Termos de Uso.',
        'reg_terms' => 'Li e aceito os {terms} e a {privacy} da Lagos Soluções.',
        'err_terms' => 'É obrigatório aceitar os Termos de Uso e a Política de Privacidade.',
        // v0.7 — conta, recuperação e segurança
        'forgot_title' => 'Recuperar acesso', 'forgot_sub' => 'Informe seu e-mail e enviaremos um link para definir uma nova senha.',
        'forgot_btn' => 'Enviar link de recuperação',
        'reset_title' => 'Definir nova senha', 'reset_sub' => 'Escolha uma nova senha para a sua conta.',
        'reset_btn' => 'Salvar nova senha',
        'reset_sent' => 'Se o e-mail estiver cadastrado, você receberá o link de recuperação em instantes.',
        'reset_ok' => 'Senha alterada com sucesso! Faça login com a nova senha.',
        'err_reset_invalid' => 'Link de recuperação inválido ou expirado. Solicite um novo.',
        'check_email' => 'Conta criada! Enviamos um link de confirmação para o seu e-mail — confirme para acessar o painel.',
        'activated_ok' => 'E-mail confirmado! Sua conta está ativa — faça login para começar.',
        'err_activation' => 'Link de confirmação inválido ou expirado. Cadastre-se novamente ou reenvie o e-mail.',
        'err_confirm_email' => 'Confirme seu e-mail antes de entrar. Verifique sua caixa de entrada (ou peça o reenvio pelo link "não recebi").',
        'cur_charged' => 'Cobrança em {cur}',
        'opt_configure' => 'Configure seu serviço',
        'opt_choose' => 'Configurar',
        'opt_total_now' => 'Total agora',
        'svc_options' => 'Configuração',
        'sso_title' => 'Acesso rápido (SSO)',
        'sso_sub' => 'Entre no painel do serviço com um clique, sem digitar senha.',
        'sso_open_panel' => 'Abrir painel',
        'sso_err' => 'Não foi possível iniciar a sessão no painel agora. Tente novamente.',
        'sso_err_status' => 'O serviço precisa estar ativo para acessar o painel.',
        'inv_doc' => 'Fatura',
        'inv_issued_to' => 'Emitida para',
        'inv_issue_date' => 'Emissão',
        'inv_due_date' => 'Vencimento',
        'inv_paid_on' => 'Paga em',
        'inv_description' => 'Descrição',
        'inv_qty' => 'Qtd',
        'inv_amount' => 'Valor',
        'inv_print' => 'Imprimir / Salvar em PDF',
        'inv_pdf_link' => 'Baixar fatura (PDF)',
        'inv_st_paid' => 'Paga',
        'inv_st_open' => 'Em aberto',
        'inv_st_overdue' => 'Vencida',
        'inv_thanks' => 'Obrigado por escolher a {company}. Qualquer dúvida, fale com o suporte.',
        'inv_service' => 'Serviço',
        'inv_generated' => 'Documento gerado por LagosPanel.',
        'err_login' => 'E-mail ou senha inválidos.', 'err_required' => 'Preencha todos os campos.',
        'err_email' => 'Use um e-mail válido.', 'err_exists' => 'Este e-mail já está cadastrado.',
        'err_match' => 'As senhas não coincidem.', 'err_short' => 'A senha precisa ter ao menos 6 caracteres.',
        'msg_logged_out' => 'Você saiu da sua conta. Até logo! ',
        'msg_welcome' => 'Conta criada com sucesso! Bem-vindo(a) à Lagos. ',
        // Menu do painel
        'menu_dashboard' => 'Painel', 'menu_services' => 'Serviços', 'menu_invoices' => 'Faturas',
        'menu_support' => 'Suporte', 'menu_store' => 'Loja', 'menu_profile' => 'Perfil',
        'menu_admin' => 'Administração', 'menu_logout' => 'Sair', 'menu_public' => 'Ver site',
        // Dashboard
        'welcome' => 'Olá, {name} ', 'welcome_sub' => 'Aqui está o resumo da sua conta.',
        'st_services' => 'Serviços ativos', 'st_invoices' => 'Faturas em aberto',
        'st_tickets' => 'Tickets abertos', 'st_balance' => 'Saldo em carteira',
        'announcements' => 'Novidades', 'quick_actions' => 'Ações rápidas',
        'qa_order' => 'Contratar novo serviço', 'qa_ticket' => 'Abrir um ticket', 'qa_invoices' => 'Ver faturas',
        'recent_invoices' => 'Faturas recentes', 'recent_services' => 'Serviços recentes',
        'no_data' => 'Nada por aqui ainda.',
        'no_services' => 'Você ainda não possui serviços. Que tal dar uma olhada na loja?',
        'no_invoices' => 'Nenhuma fatura encontrada.',
        'no_tickets' => 'Nenhum ticket por aqui. Esperamos que continue assim! ',
        // Tabelas
        'col_service' => 'Serviço', 'col_product' => 'Produto', 'col_status' => 'Status',
        'col_cycle' => 'Ciclo', 'col_price' => 'Valor', 'col_next_due' => 'Renovação',
        'col_invoice' => 'Fatura', 'col_date' => 'Data', 'col_total' => 'Total',
        'col_due' => 'Vencimento', 'col_actions' => 'Ações', 'col_subject' => 'Assunto',
        'col_dept' => 'Depto.', 'col_priority' => 'Prioridade', 'col_updated' => 'Atualizado',
        // Status
        'status_active' => 'Ativo', 'status_pending' => 'Pendente', 'status_suspended' => 'Suspenso',
        'status_cancelled' => 'Cancelado', 'status_paid' => 'Paga', 'status_unpaid' => 'Em aberto',
        'status_overdue' => 'Vencida', 'status_refunded' => 'Reembolsada', 'status_open' => 'Aberto',
        'status_answered' => 'Respondido', 'status_customer_reply' => 'Aguardando você', 'status_closed' => 'Fechado',
        'cycle_monthly' => 'mensal', 'cycle_yearly' => 'anual', 'cycle_one_time' => 'único',
        // Ações comuns
        'view' => 'Ver', 'pay' => 'Pagar', 'order' => 'Contratar', 'order_now' => 'Assinar agora',
        'buy_now' => 'Comprar agora', 'invoice' => 'Fatura',
        'order_success' => 'Pedido realizado! A fatura já está disponível em Faturas.',
        'paid_success' => 'Fatura marcada como paga! ',
        'must_login' => 'Entre na sua conta para contratar este produto.',
        // Pix
        'pix_title' => 'Pagar com Pix',
        'pix_desc' => 'Escaneie o QR Code ou copie o código abaixo para pagar a fatura {ref}. (Demonstração — o QR não é real.)',
        'pix_copy' => 'Copiar código Pix', 'pix_copied' => 'Código copiado!',
        'pix_done' => 'Já paguei',
        // Páginas
        'invoices_title' => 'Minhas faturas', 'invoices_sub' => 'Acompanhe pagamentos e vencimentos.',
        'services_title' => 'Meus serviços', 'services_sub' => 'Gerencie seus produtos contratados.',
        'store_title' => 'Loja LagosPanel', 'store_sub' => 'Escolha um plano e comece agora mesmo.',
        'all_categories' => 'Todos',
        // Suporte
        'support_title' => 'Central de suporte', 'support_sub' => 'Estamos aqui para ajudar.',
        'new_ticket' => 'Novo ticket', 'label_subject' => 'Assunto', 'label_department' => 'Departamento',
        'label_priority' => 'Prioridade', 'label_message' => 'Mensagem', 'btn_send_ticket' => 'Enviar ticket',
        'my_tickets' => 'Meus tickets', 'ticket_created' => 'Ticket enviado! Respondemos o mais breve possível.',
        'reply' => 'Responder', 'reply_sent' => 'Resposta enviada!',
        'dept_general' => 'Geral', 'dept_billing' => 'Financeiro', 'dept_tech' => 'Técnico',
        'prio_low' => 'Baixa', 'prio_medium' => 'Média', 'prio_high' => 'Alta',
        'you' => 'Você', 'staff' => 'Equipe Lagos', 'open_ticket_hint' => 'Descreva sua dúvida ou problema com o máximo de detalhes.',
        // Perfil
        'profile_title' => 'Meu perfil', 'profile_sub' => 'Gerencie seus dados e preferências.',
        'account_info' => 'Dados da conta', 'member_since' => 'Cliente desde',
        'change_pass' => 'Alterar senha', 'label_current_pass' => 'Senha atual', 'label_new_pass' => 'Nova senha',
        'btn_save' => 'Salvar alterações', 'saved_ok' => 'Alterações salvas com sucesso!',
        'wrong_pass' => 'Senha atual incorreta.',
        'api_key' => 'Chave de API', 'api_key_sub' => 'Use para integrar sistemas externos. Mantenha em segredo.',
        'btn_copy' => 'Copiar', 'btn_regen' => 'Gerar nova', 'regen_ok' => 'Nova chave de API gerada!',
        'lang_pref' => 'Idioma', 'lang_sub' => 'Escolha o idioma do painel.',
        'tfa' => 'Autenticação em dois fatores', 'tfa_sub' => 'Uma camada extra de segurança para sua conta.',
        'tfa_soon' => 'Em breve', 'add_balance' => 'Adicionar saldo',
        // Carrinho & cupons (v0.2)
        'add_to_cart' => 'Adicionar ao carrinho', 'cart_title' => 'Carrinho',
        'cart_sub' => 'Revise os itens antes de finalizar o pedido.',
        'cart_empty' => 'Seu carrinho está vazio. ', 'cart_continue' => 'Continuar comprando',
        'col_item' => 'Item', 'col_qty' => 'Qtd', 'col_subtotal' => 'Subtotal',
        'coupon_ph' => 'Cupom de desconto', 'coupon_apply' => 'Aplicar',
        'discount' => 'Desconto', 'checkout' => 'Finalizar pedido',
        'checkout_success' => 'Pedido finalizado! Suas faturas já estão disponíveis. ',
        'coupon_ok' => 'Cupom aplicado com sucesso! ', 'coupon_invalid' => 'Cupom inválido ou inativo.',
        'cart_updated' => 'Carrinho atualizado.', 'remove' => 'Remover', 'update' => 'Atualizar',
        'login_to_checkout' => 'Entre para finalizar o pedido',
        'api_hint' => 'Base da API: /wp-json/lagos/v1 — envie o header X-Lagos-Key',
        // Módulos de provisionamento (v0.3)
        'col_server' => 'Servidor',
        'prov_provisioning' => 'Provisionando…', 'prov_active' => 'Provisionado',
        'prov_suspended' => 'Suspenso no servidor', 'prov_error' => 'Erro no provisionamento',
        'prov_terminated' => 'Removido do servidor',
        // Segurança: 2FA + sessões (v0.4)
        'tfa_title' => 'Verificação em dois fatores', 'tfa_sub2' => 'Digite o código de 6 dígitos do seu aplicativo autenticador.',
        'tfa_code_label' => 'Código de verificação', 'back_login' => 'Voltar ao login',
        'err_2fa' => 'Código inválido ou expirado.',
        'tfa_setup' => 'Configurar 2FA',
        'tfa_step1' => 'Adicione esta chave no seu aplicativo autenticador (Google Authenticator, Authy, 1Password):',
        'tfa_step2' => 'Digite o código gerado pelo aplicativo para confirmar:',
        'tfa_confirm' => 'Confirmar e ativar', 'tfa_enable' => 'Ativar', 'tfa_disable' => 'Desativar',
        'tfa_disable_confirm' => 'Desativar a autenticação em dois fatores?', 'tfa_active' => 'Ativo',
        'tfa_enabled' => '2FA ativado com sucesso!', 'tfa_disabled' => '2FA desativado.',
        'tfa_invalid_code' => 'Código inválido. Tente novamente.', 'tfa_otpauth' => 'Abrir no aplicativo',
        'tfa_started' => 'Insira a chave no autenticador e confirme com o código.',
        'sessions' => 'Sessões ativas', 'sessions_current' => 'Sessão atual',
        'sessions_end_others' => 'Encerrar outras sessões', 'sessions_closed' => 'Outras sessões foram encerradas.',
        // Serviços (v0.4)
        'manage' => 'Gerenciar', 'auto_renew' => 'Renovação automática',
        'autorenew_hint' => 'Com a renovação automática ativa, geramos a fatura automaticamente a cada ciclo.',
        'autorenew_on' => 'Ativada', 'autorenew_off' => 'Desativada',
        'cancel_service' => 'Cancelar serviço', 'cancel_reason' => 'Motivo (opcional)',
        'cancel_send' => 'Enviar solicitação', 'cancel_confirm' => 'Confirmar solicitação de cancelamento?',
        'cancel_requested' => 'Cancelamento solicitado',
        'cancel_sent' => 'Solicitação enviada! Nossa equipe irá processar.',
        'cancel_hint' => 'Ao solicitar o cancelamento, nossa equipe processará o pedido e o serviço será removido no servidor.',
        'cancel_pending_hint' => 'Sua solicitação de cancelamento está em análise pela nossa equipe.',
        'created_at' => 'Criado em', 'connection' => 'Conexão', 'remote_id' => 'ID remoto',
        'back_services' => '← Voltar para meus serviços',
        // Base de conhecimento (v0.4)
        'kb_title' => 'Base de conhecimento', 'kb_sub' => 'Guias e respostas para as dúvidas mais comuns.',
        'kb_search' => 'Buscar artigo...', 'kb_search_btn' => 'Buscar', 'kb_empty' => 'Nenhum artigo encontrado.',
        // Carteira / recarga (v0.4)
        'deposit_title' => 'Adicionar saldo',
        'deposit_hint' => 'Gere uma fatura e pague via Pix — o valor é creditado na carteira. Mínimo R$ 10.',
        'deposit_ph' => 'Valor', 'deposit_btn' => 'Gerar fatura',
        'deposit_created' => 'Fatura de recarga gerada! Pague em Faturas.',
        'deposit_ok' => 'Saldo adicionado com sucesso!',
        'err_min_deposit' => 'O valor mínimo de recarga é R$ 10,00.',
        // Afiliados (v0.5)
        'af_title' => 'Programa de afiliados', 'af_sub' => 'Indique amigos e ganhe comissão em cada pagamento deles.',
        'af_refs' => 'Indicações', 'af_earnings' => 'Ganhos totais', 'af_rate' => 'Comissão por pagamento',
        'af_cookie' => 'duração do cookie', 'af_link' => 'Seu link de indicação',
        'af_link_hint' => 'Compartilhe este link. Quem se cadastrar por ele vira sua indicação por 30 dias.',
        'af_commission' => 'Comissão', 'af_credited' => 'Creditada', 'af_empty' => 'Sem comissões ainda. Compartilhe seu link!',
        // Domínios (v0.5)
        'dom_title' => 'Buscar domínio', 'dom_sub' => 'Encontre o endereço perfeito para o seu projeto.',
        'dom_ph' => 'meusite ou meusite.com', 'dom_btn' => 'Verificar',
        'dom_available' => 'Disponível', 'dom_taken' => 'Registrado', 'dom_unknown' => 'Indisponível p/ consulta',
        'dom_register' => 'Contratar', 'dom_transfer' => 'Ver planos', 'dom_hint' => 'Digite um nome acima para verificar a disponibilidade em .com, .com.br, .net e .app.',
        'dom_disclaimer' => 'Consulta em tempo real via RDAP (Registro.br e Verisign). Disponibilidade sujeita a confirmação no registro.',
        // Downloads (v0.5)
        'dl_title' => 'Downloads', 'dl_sub' => 'Guias, ferramentas e arquivos para clientes.',
        'dl_download' => 'Baixar',
        // Status da rede (v0.5)
        'net_title' => 'Status da rede', 'net_sub' => 'Acompanhe a saúde da nossa infraestrutura em tempo real.',
        'net_all_ok' => 'Todos os sistemas operacionais', 'net_all_ok_sub' => 'Nenhum incidente em andamento.',
        'net_operational' => 'Operacional', 'net_degraded' => 'Degradado',
        'net_components' => 'Componentes', 'net_component' => 'Componente',
        'net_history' => 'Histórico de incidentes',
        'net_investigating' => 'Investigando', 'net_monitoring' => 'Monitorando', 'net_resolved' => 'Resolvido',
        // Avaliação de tickets (v0.5)
        'rate_ask' => 'Como foi seu atendimento?', 'rated_ok' => 'Obrigado pela avaliação!',
        // Gateways de pagamento (v0.6)
        'gw_title' => 'Pagar fatura', 'gw_choose' => 'Escolha como quer pagar',
        'gw_methods' => 'Formas de pagamento', 'gw_total' => 'Total',
        'gw_test' => 'Ambiente de teste', 'gw_test_note' => 'nenhum valor real será cobrado — o pagamento é simulado.',
        'gw_approve' => 'Aprovar pagamento', 'gw_pay' => 'Pagar agora',
        'gw_paid_ok' => 'Pagamento confirmado!', 'gw_back_faturas' => 'Voltar às faturas',
        'gw_scan' => 'Escaneie o QR Code no app do seu banco',
        'gw_copy' => 'Copiar código', 'gw_copied' => 'Copiado!',
        'gw_redirect_note' => 'Você será levado ao checkout seguro do {name} para concluir.',
        'gw_error' => 'Não foi possível iniciar o pagamento.',
        'gw_expired' => 'Sessão de pagamento expirada. Inicie novamente.',
        'gw_invalid' => 'Link de pagamento inválido.',
        'gw_none' => 'Nenhuma forma de pagamento ativa no momento. Contate o suporte.',
        'gw_manual_done_btn' => 'Já fiz a transferência',
        'manual_pending' => 'Recebemos seu aviso! A confirmação será feita pela nossa equipe em até 2h úteis.',
        'demo_note' => 'Ambiente de demonstração — dados fictícios.',
    ],

    'en' => [
        // Brand & navigation
        'brand_tag' => 'Lagos Solutions', 'nav_home' => 'Home', 'nav_store' => 'Store',
        'nav_features' => 'Features', 'nav_pricing' => 'Pricing', 'nav_login' => 'Sign in',
        'nav_register' => 'Create account',
        // Hero
        'hero_badge' => ' A Lagos company',
        'hero_title' => 'The billing panel your business deserves',
        'hero_sub' => 'Clients, services, invoices and support in one place. More modern than WHMCS, more complete than Paymenter — built for the world.',
        'hero_cta' => 'Create my account', 'hero_cta2' => 'Browse the store',
        'stat1_v' => '2,400+', 'stat1_l' => 'clients on the platform',
        'stat2_v' => '99.9%', 'stat2_l' => 'average uptime',
        'stat3_v' => '24/7', 'stat3_l' => 'human support',
        'stat4_v' => '<80ms', 'stat4_l' => 'average API response',
        // Features
        'features_title' => 'Everything a modern panel needs',
        'features_sub' => 'Built for hosting companies that want to grow without headaches.',
        'f1t' => 'Smart billing', 'f1d' => 'Recurring invoices, Pix and card payments, automatic reminders and real-time reconciliation.',
        'f2t' => 'Automatic provisioning', 'f2d' => 'Hosting, VPS and domains created instantly after payment confirmation.',
        'f3t' => 'Support center', 'f3d' => 'Tickets with departments, priorities, email replies and configurable SLA.',
        'f4t' => 'Native multi-language', 'f4d' => 'Portuguese and English built-in — clients switch with one click.',
        'f5t' => 'Security first', 'f5d' => '2FA, per-client API keys, audit logs and encryption at rest.',
        'f6t' => 'API & Webhooks', 'f6d' => 'Full REST API to integrate provisioners, gateways and external panels.',
        // Pricing
        'pricing_title' => 'Plans ready to sell',
        'pricing_sub' => 'Demo products pre-loaded — customize everything in the admin.',
        'from' => 'from', 'month' => '/mo', 'year' => '/yr', 'one_time' => '/one-time',
        'cta_title' => 'Ready to level up your business?',
        'cta_sub' => 'Create your account now and see LagosPanel in action.',
        'cta_btn' => 'Get started', 'view_all' => 'View all',
        'featured_badge' => 'Best seller',
        // Footer
        'footer_tag' => 'The next-gen panel for hosting companies. A Lagos Company product.',
        'footer_product' => 'Product', 'footer_company' => 'Company', 'footer_legal' => 'Legal',
        'footer_about' => 'About', 'footer_contact' => 'Contact',
        'footer_terms' => 'Terms of use', 'footer_privacy' => 'Privacy',
        'footer_rights' => 'All rights reserved.',
        // Auth
        'login_title' => 'Welcome back', 'login_sub' => 'Access your LagosPanel account',
        'register_title' => 'Create your account', 'register_sub' => "It takes less than a minute.",
        'label_email' => 'Email', 'label_password' => 'Password', 'label_name' => 'Full name',
        'label_confirm' => 'Confirm password', 'label_phone' => 'Phone',
        'remember' => 'Keep me signed in', 'forgot' => 'Forgot your password?',
        'btn_login' => 'Sign in', 'btn_register' => 'Create account',
        'no_account' => "Don't have an account?", 'have_account' => 'Already have an account?',
        'terms' => 'By continuing you agree to the Terms of Use.',
        'reg_terms' => 'I have read and accept the {terms} and the {privacy} of Lagos Company.',
        'err_terms' => 'You must accept the Terms of Use and the Privacy Policy.',
        // v0.7 — account, recovery and security
        'forgot_title' => 'Recover access', 'forgot_sub' => 'Enter your e-mail and we will send you a link to set a new password.',
        'forgot_btn' => 'Send recovery link',
        'reset_title' => 'Set a new password', 'reset_sub' => 'Choose a new password for your account.',
        'reset_btn' => 'Save new password',
        'reset_sent' => 'If the e-mail is registered, you will receive the recovery link shortly.',
        'reset_ok' => 'Password changed! Sign in with your new password.',
        'err_reset_invalid' => 'Invalid or expired recovery link. Request a new one.',
        'check_email' => 'Account created! We sent a confirmation link to your e-mail — confirm it to access the panel.',
        'activated_ok' => 'E-mail confirmed! Your account is active — sign in to get started.',
        'err_activation' => 'Invalid or expired confirmation link. Register again or resend the e-mail.',
        'err_confirm_email' => 'Confirm your e-mail before signing in. Check your inbox (or request a resend).',
        'cur_charged' => 'Charged in {cur}',
        'opt_configure' => 'Configure your service',
        'opt_choose' => 'Configure',
        'opt_total_now' => 'Total now',
        'svc_options' => 'Configuration',
        'sso_title' => 'Quick access (SSO)',
        'sso_sub' => "Sign in to this service's panel with one click, no password needed.",
        'sso_open_panel' => 'Open panel',
        'sso_err' => 'Could not start a panel session right now. Please try again.',
        'sso_err_status' => 'The service must be active to access the panel.',
        'inv_doc' => 'Invoice',
        'inv_issued_to' => 'Issued to',
        'inv_issue_date' => 'Issued',
        'inv_due_date' => 'Due date',
        'inv_paid_on' => 'Paid on',
        'inv_description' => 'Description',
        'inv_qty' => 'Qty',
        'inv_amount' => 'Amount',
        'inv_print' => 'Print / Save as PDF',
        'inv_pdf_link' => 'Download invoice (PDF)',
        'inv_st_paid' => 'Paid',
        'inv_st_open' => 'Open',
        'inv_st_overdue' => 'Overdue',
        'inv_thanks' => 'Thank you for choosing {company}. Need help? Contact support.',
        'inv_service' => 'Service',
        'inv_generated' => 'Document generated by LagosPanel.',
        'err_login' => 'Invalid email or password.', 'err_required' => 'Please fill in all fields.',
        'err_email' => 'Please use a valid email.', 'err_exists' => 'This email is already registered.',
        'err_match' => 'Passwords do not match.', 'err_short' => 'Password must be at least 6 characters.',
        'msg_logged_out' => 'You have been logged out. See you! ',
        'msg_welcome' => 'Account created successfully! Welcome to Lagos. ',
        // Panel menu
        'menu_dashboard' => 'Dashboard', 'menu_services' => 'Services', 'menu_invoices' => 'Invoices',
        'menu_support' => 'Support', 'menu_store' => 'Store', 'menu_profile' => 'Profile',
        'menu_admin' => 'Administration', 'menu_logout' => 'Log out', 'menu_public' => 'View site',
        // Dashboard
        'welcome' => 'Hello, {name} ', 'welcome_sub' => "Here's your account overview.",
        'st_services' => 'Active services', 'st_invoices' => 'Open invoices',
        'st_tickets' => 'Open tickets', 'st_balance' => 'Account balance',
        'announcements' => 'Announcements', 'quick_actions' => 'Quick actions',
        'qa_order' => 'Order new service', 'qa_ticket' => 'Open a ticket', 'qa_invoices' => 'View invoices',
        'recent_invoices' => 'Recent invoices', 'recent_services' => 'Recent services',
        'no_data' => 'Nothing here yet.',
        'no_services' => "You don't have services yet. How about checking the store?",
        'no_invoices' => 'No invoices found.',
        'no_tickets' => 'No tickets here. Hope it stays that way! ',
        // Tables
        'col_service' => 'Service', 'col_product' => 'Product', 'col_status' => 'Status',
        'col_cycle' => 'Cycle', 'col_price' => 'Amount', 'col_next_due' => 'Renews',
        'col_invoice' => 'Invoice', 'col_date' => 'Date', 'col_total' => 'Total',
        'col_due' => 'Due', 'col_actions' => 'Actions', 'col_subject' => 'Subject',
        'col_dept' => 'Dept.', 'col_priority' => 'Priority', 'col_updated' => 'Updated',
        // Status
        'status_active' => 'Active', 'status_pending' => 'Pending', 'status_suspended' => 'Suspended',
        'status_cancelled' => 'Cancelled', 'status_paid' => 'Paid', 'status_unpaid' => 'Unpaid',
        'status_overdue' => 'Overdue', 'status_refunded' => 'Refunded', 'status_open' => 'Open',
        'status_answered' => 'Answered', 'status_customer_reply' => 'Awaiting you', 'status_closed' => 'Closed',
        'cycle_monthly' => 'monthly', 'cycle_yearly' => 'yearly', 'cycle_one_time' => 'one-time',
        // Common actions
        'view' => 'View', 'pay' => 'Pay', 'order' => 'Order', 'order_now' => 'Subscribe now',
        'buy_now' => 'Buy now', 'invoice' => 'Invoice',
        'order_success' => 'Order placed! The invoice is already available in Invoices.',
        'paid_success' => 'Invoice marked as paid! ',
        'must_login' => 'Sign in to your account to order this product.',
        // Pix
        'pix_title' => 'Pay with Pix',
        'pix_desc' => 'Scan the QR code or copy the code below to pay invoice {ref}. (Demo — QR is not real.)',
        'pix_copy' => 'Copy Pix code', 'pix_copied' => 'Code copied!',
        'pix_done' => 'I already paid',
        // Pages
        'invoices_title' => 'My invoices', 'invoices_sub' => 'Track payments and due dates.',
        'services_title' => 'My services', 'services_sub' => 'Manage your subscribed products.',
        'store_title' => 'LagosPanel Store', 'store_sub' => 'Pick a plan and get started right away.',
        'all_categories' => 'All',
        // Support
        'support_title' => 'Support center', 'support_sub' => "We're here to help.",
        'new_ticket' => 'New ticket', 'label_subject' => 'Subject', 'label_department' => 'Department',
        'label_priority' => 'Priority', 'label_message' => 'Message', 'btn_send_ticket' => 'Submit ticket',
        'my_tickets' => 'My tickets', 'ticket_created' => "Ticket submitted! We'll reply as soon as possible.",
        'reply' => 'Reply', 'reply_sent' => 'Reply sent!',
        'dept_general' => 'General', 'dept_billing' => 'Billing', 'dept_tech' => 'Technical',
        'prio_low' => 'Low', 'prio_medium' => 'Medium', 'prio_high' => 'High',
        'you' => 'You', 'staff' => 'Lagos Staff', 'open_ticket_hint' => 'Describe your question or issue in as much detail as possible.',
        // Profile
        'profile_title' => 'My profile', 'profile_sub' => 'Manage your data and preferences.',
        'account_info' => 'Account info', 'member_since' => 'Client since',
        'change_pass' => 'Change password', 'label_current_pass' => 'Current password', 'label_new_pass' => 'New password',
        'btn_save' => 'Save changes', 'saved_ok' => 'Changes saved successfully!',
        'wrong_pass' => 'Current password is incorrect.',
        'api_key' => 'API key', 'api_key_sub' => 'Use it to integrate external systems. Keep it secret.',
        'btn_copy' => 'Copy', 'btn_regen' => 'Regenerate', 'regen_ok' => 'New API key generated!',
        'lang_pref' => 'Language', 'lang_sub' => 'Choose the panel language.',
        'tfa' => 'Two-factor authentication', 'tfa_sub' => 'An extra layer of security for your account.',
        'tfa_soon' => 'Coming soon', 'add_balance' => 'Add balance',
        // Cart & coupons (v0.2)
        'add_to_cart' => 'Add to cart', 'cart_title' => 'Cart',
        'cart_sub' => 'Review your items before checking out.',
        'cart_empty' => 'Your cart is empty. ', 'cart_continue' => 'Continue shopping',
        'col_item' => 'Item', 'col_qty' => 'Qty', 'col_subtotal' => 'Subtotal',
        'coupon_ph' => 'Discount coupon', 'coupon_apply' => 'Apply',
        'discount' => 'Discount', 'checkout' => 'Checkout',
        'checkout_success' => 'Order placed! Your invoices are ready. ',
        'coupon_ok' => 'Coupon applied successfully! ', 'coupon_invalid' => 'Invalid or inactive coupon.',
        'cart_updated' => 'Cart updated.', 'remove' => 'Remove', 'update' => 'Update',
        'login_to_checkout' => 'Sign in to complete your order',
        'api_hint' => 'API base: /wp-json/lagos/v1 — send the X-Lagos-Key header',
        // Provisioning modules (v0.3)
        'col_server' => 'Server',
        'prov_provisioning' => 'Provisioning…', 'prov_active' => 'Provisioned',
        'prov_suspended' => 'Suspended on server', 'prov_error' => 'Provisioning failed',
        'prov_terminated' => 'Terminated on server',
        // Security: 2FA + sessions (v0.4)
        'tfa_title' => 'Two-factor verification', 'tfa_sub2' => 'Enter the 6-digit code from your authenticator app.',
        'tfa_code_label' => 'Verification code', 'back_login' => 'Back to login',
        'err_2fa' => 'Invalid or expired code.',
        'tfa_setup' => 'Set up 2FA',
        'tfa_step1' => 'Add this key to your authenticator app (Google Authenticator, Authy, 1Password):',
        'tfa_step2' => 'Enter the code generated by the app to confirm:',
        'tfa_confirm' => 'Confirm and enable', 'tfa_enable' => 'Enable', 'tfa_disable' => 'Disable',
        'tfa_disable_confirm' => 'Disable two-factor authentication?', 'tfa_active' => 'Active',
        'tfa_enabled' => '2FA enabled successfully!', 'tfa_disabled' => '2FA disabled.',
        'tfa_invalid_code' => 'Invalid code. Try again.', 'tfa_otpauth' => 'Open in app',
        'tfa_started' => 'Enter the key in your authenticator and confirm with a code.',
        'sessions' => 'Active sessions', 'sessions_current' => 'Current session',
        'sessions_end_others' => 'End other sessions', 'sessions_closed' => 'Other sessions were ended.',
        // Services (v0.4)
        'manage' => 'Manage', 'auto_renew' => 'Auto-renewal',
        'autorenew_hint' => 'With auto-renewal on, we generate the invoice automatically each cycle.',
        'autorenew_on' => 'Enabled', 'autorenew_off' => 'Disabled',
        'cancel_service' => 'Cancel service', 'cancel_reason' => 'Reason (optional)',
        'cancel_send' => 'Send request', 'cancel_confirm' => 'Confirm cancellation request?',
        'cancel_requested' => 'Cancellation requested',
        'cancel_sent' => 'Request sent! Our team will process it.',
        'cancel_hint' => 'Once requested, our team will process the cancellation and remove the service from the server.',
        'cancel_pending_hint' => 'Your cancellation request is being reviewed by our team.',
        'created_at' => 'Created', 'connection' => 'Connection', 'remote_id' => 'Remote ID',
        'back_services' => '← Back to my services',
        // Knowledge base (v0.4)
        'kb_title' => 'Knowledge base', 'kb_sub' => 'Guides and answers to the most common questions.',
        'kb_search' => 'Search articles...', 'kb_search_btn' => 'Search', 'kb_empty' => 'No articles found.',
        // Wallet / deposits (v0.4)
        'deposit_title' => 'Add balance',
        'deposit_hint' => 'Generate an invoice and pay via Pix — the amount is credited to your wallet. Min R$ 10.',
        'deposit_ph' => 'Amount', 'deposit_btn' => 'Generate invoice',
        'deposit_created' => 'Top-up invoice generated! Pay it in Invoices.',
        'deposit_ok' => 'Balance added successfully!',
        'err_min_deposit' => 'Minimum top-up is R$ 10.00.',
        // Affiliates (v0.5)
        'af_title' => 'Affiliate program', 'af_sub' => 'Refer friends and earn commission on every payment.',
        'af_refs' => 'Referrals', 'af_earnings' => 'Total earnings', 'af_rate' => 'Commission per payment',
        'af_cookie' => 'cookie duration', 'af_link' => 'Your referral link',
        'af_link_hint' => 'Share this link. Anyone signing up through it becomes your referral for 30 days.',
        'af_commission' => 'Commission', 'af_credited' => 'Credited', 'af_empty' => 'No commissions yet. Share your link!',
        // Domains (v0.5)
        'dom_title' => 'Domain search', 'dom_sub' => 'Find the perfect address for your project.',
        'dom_ph' => 'mysite or mysite.com', 'dom_btn' => 'Check',
        'dom_available' => 'Available', 'dom_taken' => 'Registered', 'dom_unknown' => 'Cannot check now',
        'dom_register' => 'Order', 'dom_transfer' => 'View plans', 'dom_hint' => 'Type a name above to check availability on .com, .com.br, .net and .app.',
        'dom_disclaimer' => 'Live lookup via RDAP (Registro.br and Verisign). Availability subject to registry confirmation.',
        // Downloads (v0.5)
        'dl_title' => 'Downloads', 'dl_sub' => 'Guides, tools and files for clients.',
        'dl_download' => 'Download',
        // Network status (v0.5)
        'net_title' => 'Network status', 'net_sub' => 'Track the health of our infrastructure in real time.',
        'net_all_ok' => 'All systems operational', 'net_all_ok_sub' => 'No ongoing incidents.',
        'net_operational' => 'Operational', 'net_degraded' => 'Degraded',
        'net_components' => 'Components', 'net_component' => 'Component',
        'net_history' => 'Incident history',
        'net_investigating' => 'Investigating', 'net_monitoring' => 'Monitoring', 'net_resolved' => 'Resolved',
        // Ticket rating (v0.5)
        'rate_ask' => 'How was your support experience?', 'rated_ok' => 'Thanks for your feedback!',
        // Payment gateways (v0.6)
        'gw_title' => 'Pay invoice', 'gw_choose' => 'Choose how to pay',
        'gw_methods' => 'Payment methods', 'gw_total' => 'Total',
        'gw_test' => 'Test environment', 'gw_test_note' => 'no real charge — the payment is simulated.',
        'gw_approve' => 'Approve payment', 'gw_pay' => 'Pay now',
        'gw_paid_ok' => 'Payment confirmed!', 'gw_back_faturas' => 'Back to invoices',
        'gw_scan' => 'Scan the QR code with your bank app',
        'gw_copy' => 'Copy code', 'gw_copied' => 'Copied!',
        'gw_redirect_note' => 'You will be redirected to {name} secure checkout to finish.',
        'gw_error' => 'Could not start the payment.',
        'gw_expired' => 'Payment session expired. Please start again.',
        'gw_invalid' => 'Invalid payment link.',
        'gw_none' => 'No active payment method. Please contact support.',
        'gw_manual_done_btn' => 'I have made the transfer',
        'manual_pending' => 'We got your notice! Our team will confirm within 2 business hours.',
        'demo_note' => 'Demo environment — sample data.',
    ],
    ];
    return $d;
}

function lagos_current_lang() {
    if (isset($_COOKIE['lagos_lang'])) {
        $l = $_COOKIE['lagos_lang'];
        if (in_array($l, ['pt', 'en'], true)) return $l;
    }
    return 'pt';
}

function lagos_t($key, $args = []) {
    $d = lagos_i18n();
    $l = lagos_current_lang();
    $s = isset($d[$l][$key]) ? $d[$l][$key] : (isset($d['pt'][$key]) ? $d['pt'][$key] : $key);
    if ($args) $s = strtr($s, $args);
    return $s;
}

function lagos_e($key, $args = []) { echo lagos_t($key, $args); }

/** Troca de idioma via ?lang=xx (cookie + redirect limpo) */
add_action('init', function () {
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['pt', 'en'], true)) {
        setcookie('lagos_lang', $_GET['lang'], time() + 31536000, '/');
        $uri   = $_SERVER['REQUEST_URI'] ?? '/';
        $parts = parse_url($uri);
        $path  = $parts['path'] ?? '/';
        parse_str($parts['query'] ?? '', $q);
        unset($q['lang']);
        $target = $path . ($q ? '?' . http_build_query($q) : '');
        wp_safe_redirect(home_url($target));
        exit;
    }
});

/** Seletor de idioma (PT | EN) */
function lagos_lang_switcher() {
    $cur = lagos_current_lang();
    $out = '<div class="lang-switch" aria-label="Idioma">';
    foreach (['pt', 'en'] as $code) {
        $active = $code === $cur ? ' is-active' : '';
        $out .= '<a class="lang-btn' . $active . '" href="' . esc_url(add_query_arg('lang', $code)) . '" hreflang="' . $code . '">' . strtoupper($code) . '</a>';
    }
    return $out . '</div>';
}
