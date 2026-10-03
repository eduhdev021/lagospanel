#!/usr/bin/env python3
"""LagosPanel — construtor da galeria de screenshots (embutida, sem dependências)"""
import base64, os, subprocess

SHOTS = "/home/user/lagospanel/screenshots"
OUT = os.path.join(SHOTS, "galeria.html")

# (arquivo, título, descrição, tag, destaque)
ITEMS = [
    # ── v0.10: Opções, SSO & Tickets por e-mail ──
    ("70-produto-opcoes.jpg", "Opções configuráveis — estilo WHMCS (NOVO)", "Cada produto pode ter opções (slider, lista, radio, texto livre) com acréscimo de preço: RAM 2/4/8 GB, backup extra, notas... O total atualiza ao vivo, no pedido, no carrinho e na fatura.", None, "novo"),
    ("71-servico-sso.jpg", "SSO — abrir painel com 1 clique (NOVO)", "O cliente entra no painel do serviço (Pterodactyl, cPanel) direto da área dele, sem digitar senha — credencial rotativa de uso único no Pterodactyl e sessão temporária via WHM no cPanel.", None, "novo"),
    ("72-admin-email-piping.jpg", "Tickets por e-mail / IMAP (NOVO)", "O cliente responde o e-mail e a resposta cai no ticket certo (tag [LAGOS-#ID]); e-mails novos viram tickets automáticos. Cliente IMAP embutido, verificação a cada 15 min ou manual.", None, "novo"),
    # ── v0.9: Relatórios, Fatura PDF & Webhooks ──
    ("67-admin-relatorios.jpg", "Relatórios Financeiros (NOVO)", "Receita dos últimos 12 meses em gráfico, MRR estimado, ticket médio, inadimplência com dias de atraso e churn — tudo calculado das faturas reais do painel.", None, "novo"),
    ("68-fatura-pdf.jpg", "Fatura em PDF (NOVO)", "Documento A4 imprimível (Ctrl+P → salvar em PDF) com logo, dados do cliente, itens e totais — bilíngue, com equivalência na moeda escolhida pelo cliente.", None, "novo"),
    ("69-admin-webhooks.jpg", "Webhooks de saída (NOVO)", "Notifique sistemas externos em eventos (fatura paga, serviço ativado, novo cliente) via POST JSON assinado com HMAC-SHA256, com histórico de entregas.", None, "novo"),
    # ── v0.8: Multi-moeda + White-label ──
    ("64-loja-usd.jpg", "Loja em dólares (NOVO)", "Multi-moeda: o cliente escolhe R$, US$ ou € no topo e todos os preços, faturas e cobranças convertem na hora — taxas manuais ou atualizadas pelo Banco Central Europeu.", None, "novo"),
    ("65-checkout-usd.jpg", "Checkout cobrando em USD (NOVO)", "Gateways internacionais cobram na moeda escolhida (Stripe, PayPal, Mollie...); gateways Pix sempre em reais. Faturas seguem armazenadas na moeda base.", None, "novo"),
    ("66-login-lagos.jpg", "Login da equipe — white-label (NOVO)", "Sem referências ao WordPress na interface: meta tags removidas, tela de login com a marca Lagos e remetente de e-mails próprio.", None, "novo"),
    # ── v0.7.2: Atividade recente \+ exports \+ confirmação de e-mail por padrão ──
    ("63-admin-atividade-exports.jpg", "Dashboard — Atividade & Exportação (NOVO)", "Widget de atividade recente com os últimos eventos da auditoria e exportação CSV de clientes, faturas e auditoria (Excel-friendly). Confirmação de e-mail agora ativa por padrão no cadastro.", None, "novo"),
    # ── v0.7: Admin estilo WHMCS \+ propriedade Lagos Soluções ──
    ("59-admin-whmcs-dashboard.jpg", "Admin estilo WHMCS — Dashboard (NOVO)", "Topbar roxa com busca global e sino de notificações, sidebar escura por seções e dashboard em tela cheia — a experiência de administração do WHMCS, com a identidade Lagos.", None, "novo"),
    ("60-admin-whmcs-notificacoes.jpg", "Notificações pendentes (NOVO)", "Sino com contagem em tempo real: faturas em aberto, tickets e transferências aguardando confirmação — acesso direto a cada lista.", None, "novo"),
    ("61-admin-whmcs-clientes.jpg", "Admin WHMCS — Clientes (NOVO)", "Gestão de clientes dentro do shell WHMCS: avatar, serviços, total pago, carteira e último acesso.", None, "novo"),
    ("62-admin-whmcs-faturas.jpg", "Admin WHMCS — Faturas (NOVO)", "Listas de faturas, produtos, serviços e tickets também rodam no shell, com menu lateral em todas as páginas.", None, "novo"),
    # ── v0.7: Conta, segurança e auditoria ──
    ("57-admin-auditoria.jpg", "Logs de Auditoria (NOVO)", "Cada evento sensível registrado: logins e falhas, trocas de senha, 2FA, pagamentos, webhooks, provisionamentos, cron — com usuário, IP e data/hora.", None, "novo"),
    ("56-admin-clientes.jpg", "Admin — Clientes (NOVO)", "CRUD estilo WHMCS: avatar, serviços ativos, total pago, carteira, último acesso e status de confirmação de e-mail por cliente.", None, "novo"),
    ("54-perfil-gravatar.jpg", "Perfil com Gravatar (NOVO)", "Avatar universal do Gravatar no perfil e na sidebar — e-mails com 2FA, alerta de novo acesso e redefinição de senha.", None, "novo"),
    ("55-login-esqueci.jpg", "Recuperar acesso (NOVO)", "Fluxo completo de redefinição de senha por e-mail + confirmação de conta ao cadastrar (anti-enumeration de e-mails).", None, "novo"),
    ("58-admin-modulos-whmcs.jpg", "Conexões — módulos WHMCS (NOVO)", "Os mesmos módulos do WHMCS: Pterodactyl, cPanel, DirectAdmin, Plesk, aaPanel, Virtualizor, Proxmox VE, VirtFusion, Cloudflare DNS e API custom.", None, "novo"),
    # ── v0.6: LGPD & Deploy ──
    ("53-cadastro-lgpd.jpg", "Cadastro com aceite LGPD (NOVO)", "Checkbox obrigatório de Termos de Uso e Política de Privacidade — sem aceite, a conta não é criada. Registro do aceite com data e IP fica no perfil do usuário.", None, "novo"),
    ("51-termos-de-uso.jpg", "Termos de Uso (NOVO)", "Página legal completa (10 seções): contratação, renovação, suspensão por inadimplência, uso aceitável, reembolsos — editável no admin.", None, "novo"),
    ("52-privacidade-mobile.jpg", "Política de Privacidade — mobile (NOVO)", "11 seções alinhadas à LGPD: dados coletados, bases legais, compartilhamento com gateways, direitos do titular e contato do DPO.", None, "novo"),
    # ── v0.6: Endurecimento para produção ──
    ("49-admin-producao.jpg", "Admin — Avisos de produção (NOVO)", "Aviso vermelho quando há gateway em modo teste, transferências aguardando confirmação e status da automação diária (renovações e suspensões).", None, "novo"),
    ("50-admin-modo-producao.jpg", "Modo produção & Automação (NOVO)", "Alternância demo/produção, geração de faturas de renovação e suspensão por inadimplência configuráveis.", None, "novo"),
    # ── v0.6: Gateways estilo Paymenter ──
    ("41-checkout-metodos.jpg", "Checkout — Formas de pagamento (NOVO)", "Escolha do gateway como no Paymenter: cards com ícone, métodos aceitos e selo de ambiente de teste.", None, "novo"),
    ("43-checkout-mp-sandbox.jpg", "Mercado Pago — modo teste (NOVO)", "Sandbox da integração oficial: aprovação simulada sem cobrança real — perfeito para homologar.", None, "novo"),
    ("42-checkout-pix.jpg", "Pix com QR Code (NOVO)", "QR + copia-e-cola na tela; na produção, QR real gerado pela API do Mercado Pago.", None, "novo"),
    ("44-checkout-manual.jpg", "Transferência bancária (NOVO)", "Gateway manual com instruções ao cliente e aviso de pagamento para a equipe confirmar.", None, "novo"),
    ("45-admin-gateways.jpg", "Admin — Gateways de pagamento (NOVO)", "Pix, Mercado Pago, Stripe, PayPal, Mollie, Coinbase Commerce e manual — ative, ordene e configure cada um.", None, "novo"),
    ("46-admin-gateway-mp.jpg", "Admin — Configuração do gateway (NOVO)", "Credenciais, modo teste, URL de webhook pronta para cadastrar no provedor.", None, "novo"),
    ("47-mobile360-checkout.jpg", "Mobile 360px — Checkout (NOVO)", "Formas de pagamento empilhadas em um cartão por linha, alvos de toque generosos.", None, "novo"),
    ("48-mobile360-mp.jpg", "Mobile 360px — Pagamento (NOVO)", "Página do gateway perfeita no menor celular.", None, "novo"),
    # ── v0.5: Mobile Perfection ──
    ("37-mobile360-home.jpg", "Mobile 360px — Home (NOVO)", "Landing no menor celular comum (360px) sem quebra: header compacto, tudo no lugar.", None, "novo"),
    ("39-mobile360-painel.jpg", "Mobile 360px — Painel do cliente (NOVO)", "Dashboard completo em 360px: cards fluidos, menu lateral retrátil, alvos de toque de 36px.", None, "novo"),
    ("40-mobile360-faturas-cards.jpg", "Mobile 360px — Faturas viram cards (NOVO)", "Tabelas viram cards empilhados com rótulos (data-label): faturas legíveis no celular.", None, "novo"),
    ("38-mobile360-dominios.jpg", "Mobile 360px — Busca de domínios (NOVO)", "Consulta RDAP em 360px, resultados em cards, botões grandes para o polegar.", None, "novo"),
    # ── v0.5: Recursos novos ──
    ("31-dominios.jpg", "Busca de domínios — RDAP real (NOVO)", "Consulta ao RDAP oficial (registro.br, Verisign, rdap.org): disponível/registrado com opção de registrar ou transferir.", None, "novo"),
    ("34-afiliados.jpg", "Programa de afiliados (NOVO)", "Link de indicação com código próprio, comissão configurável (padrão 10%), carteira e histórico — como no WHMCS.", None, "novo"),
    ("35-suporte-avaliacao.jpg", "Avaliação de atendimento (NOVO)", "Ticket fechado recebe estrelas de 1 a 5; a média alimenta o card de satisfação do admin.", None, "novo"),
    ("32-downloads.jpg", "Central de downloads (NOVO)", "Arquivos por produto gerenciáveis no admin, com ícone e botão de baixar.", None, "novo"),
    ("33-status.jpg", "Status da rede (NOVO)", "Página pública estilo status page: componentes operacionais e histórico de incidentes com prazos.", None, "novo"),
    ("10-admin.jpg", "Admin — Dashboard com gráfico (NOVO)", "Home admin estilo WHMCS: faturamento mensal em gráfico, MRR estimado, satisfação média e atalhos.", None, "novo"),
    ("22-admin-modulos.jpg", "Admin — Conexões & Módulos", "Pterodactyl, cPanel/WHM, aaPanel, Virtualizor, Cloudflare DNS e API custom, com teste de conexão.", "completa", None),
    ("10b-admin-config.jpg", "Admin — Configurações & SMTP", "Loja, cupons, e-mail SMTP com teste e log, e taxa de comissão de afiliados.", "completa", None),
    # ── Segurança (v0.4) ──
    ("24-servicos-cards.jpg", "Serviços em cards", "Página de serviços estilo Paymenter: cards com ícone do módulo, status, provisionamento, preço e renovação.", "completa", None),
    ("25-servico-detalhe.jpg", "Gerenciar serviço", "Detalhe do serviço: conexão e ID remoto, renovação automática (toggle), solicitação de cancelamento.", "completa", None),
    ("26-perfil-seguranca.jpg", "Perfil — 2FA e sessões", "Autenticação em dois fatores real (TOTP), sessões ativas com IP/dispositivo e recarga de saldo.", "completa", None),
    ("28-tela-2fa.jpg", "Login com 2FA", "Segunda etapa do login: código de 6 dígitos do aplicativo autenticador. Desativado por padrão — o usuário ativa.", None, None),
    ("29-painel-pos-2fa.jpg", "Painel após 2FA", "Acesso concluído com o código correto.", None, None),
    ("27-kb.jpg", "Base de conhecimento", "Busca, categorias e artigos em acordeão — gerenciável no admin.", "completa", None),
    ("27b-kb-artigo.jpg", "Artigo da KB aberto", "Conteúdo do artigo expandido.", None, None),
    # ── Base ──
    ("01-home.jpg", "Landing page", "Identidade roxa oficial com as logos Lagos.", "completa", None),
    ("02-loja.jpg", "Loja", "7 produtos em 5 categorias.", "completa", None),
    ("03-produto.jpg", "Página de produto", "Minecraft via Pterodactyl.", None, None),
    ("04-login.jpg", "Login", "Acesso da área do cliente.", None, None),
    ("05-painel.jpg", "Painel do cliente", "Dashboard com estatísticas e ações rápidas.", None, None),
    ("06-faturas.jpg", "Faturas", "Status coloridos e botões Pix.", None, None),
    ("07-pix.jpg", "Pagamento Pix", "QR Code e copia-e-cola.", None, None),
    ("23-servicos-provisionados.jpg", "Provisionamento automático", "Serviços criados nos painéis remotos ao pagar a fatura.", "completa", None),
    ("08-suporte.jpg", "Central de suporte", "Tickets com departamentos e prioridades.", "completa", None),
    ("09-perfil.jpg", "Perfil", "Dados, senha, chave de API e idioma.", "completa", None),
    ("13-carrinho.jpg", "Carrinho", "Multi-item com quantidades e totais.", "completa", None),
    ("14-carrinho-cupom.jpg", "Cupom aplicado", "LAGOS10 com desconto automático.", None, None),
    ("15-dark-home.jpg", "Dark — Home", "Tema escuro roxo com logo branca.", None, None),
    ("16-dark-painel.jpg", "Dark — Painel", "Área do cliente escura com logo branca.", None, None),
    ("17-dark-loja.jpg", "Dark — Loja", "Loja em tema escuro.", None, None),
    ("11-mobile-home.jpg", "Mobile — Home", "Landing responsiva.", None, None),
    ("12-mobile-painel.jpg", "Mobile — Painel", "Menu lateral retrátil.", None, None),
]

def build():
    cards = []
    for fname, titulo, desc, tag, destaque in ITEMS:
        path = os.path.join(SHOTS, fname)
        if not os.path.exists(path):
            continue
        with open(path, "rb") as f:
            b64 = base64.b64encode(f.read()).decode()
        tag_html = f'<span class="tag">{tag}</span>' if tag else ""
        cls = "card novo" if destaque == "novo" else "card"
        cards.append(f'''
    <figure class="{cls}">
      <figcaption>
        <h2>{titulo}</h2>
        <p>{desc}</p>
        {tag_html}
      </figcaption>
      <img src="data:image/jpeg;base64,{b64}" alt="{titulo}" loading="lazy">
    </figure>''')

    data = subprocess.run(["date", "+%d/%m/%Y %H:%M"], capture_output=True, text=True).stdout.strip()
    html = f'''<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>LagosPanel — Screenshots</title>
<style>
  :root {{ --ink:#120B24; --line:#E3E9F4; --muted:#5D6E8C; }}
  * {{ box-sizing:border-box; margin:0; padding:0; }}
  body {{ font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; background:#F4F7FC; color:#12203A; padding:40px 20px 80px; }}
  .wrap {{ max-width:1080px; margin:0 auto; }}
  header.hero {{ text-align:center; margin-bottom:44px; }}
  .hero h1 {{ font-size:clamp(1.6rem,4vw,2.3rem); color:#fff; letter-spacing:-.02em; }}
  .hero p {{ color:#A9BAD6; margin-top:8px; font-size:1.02rem; }}
  header.hero {{ background: radial-gradient(900px 400px at 20% -20%, rgba(124,58,237,.35), transparent 60%), radial-gradient(700px 400px at 90% 0%, rgba(192,64,224,.25), transparent 55%), var(--ink); border-radius:24px; padding:52px 24px; }}
  .card {{ background:#fff; border:1px solid var(--line); border-radius:18px; overflow:hidden; margin:26px 0; box-shadow:0 8px 24px rgba(16,30,54,.07); }}
  .card.novo {{ border:2px solid #7C3AED; box-shadow:0 12px 32px rgba(124,58,237,.2); }}
  figcaption {{ padding:20px 24px 14px; display:flex; align-items:baseline; gap:14px; flex-wrap:wrap; }}
  figcaption h2 {{ font-size:1.08rem; }}
  figcaption p {{ color:var(--muted); font-size:.92rem; flex:1 1 320px; }}
  .tag {{ background:#EDE9FE; color:#7C3AED; font-size:.7rem; font-weight:700; padding:4px 10px; border-radius:99px; letter-spacing:.04em; text-transform:uppercase; }}
  img {{ display:block; width:100%; border-top:1px solid var(--line); }}
  footer {{ text-align:center; color:var(--muted); font-size:.85rem; margin-top:40px; }}
  footer b {{ color:#12203A; }}
</style>
</head>
<body>
<div class="wrap">
  <header class="hero">
    <h1>LagosPanel v0.10 — Opções, SSO & Tickets por E-mail</h1>
    <p>68 telas — opções configuráveis, SSO, tickets por e-mail, relatórios, multi-moeda, white-label, atividade e exportação, auditoria, clientes, e-mails de conta, modo produção, automação diária e gateways de pagamento dos mesmos tipos do Paymenter (Stripe, PayPal, Mollie, Mercado Pago, Coinbase, manual, Pix), checkout mobile-first e todo o resto.</p>
  </header>
  {''.join(cards)}
  <footer>Screenshots gerados em {data} · <b>LagosPanel v0.10</b> · Um produto da Companhia Lagos</footer>
</div>
</body>
</html>'''
    with open(OUT, "w") as f:
        f.write(html)
    print(f"galeria: {os.path.getsize(OUT)/1024/1024:.2f} MB · {len(cards)} capturas")

if __name__ == "__main__":
    build()
