# Entrega — LagosPanel independente 1.0.0-alpha.5

## Implementado nesta rodada

- aaPanel nativo: criação, consulta, suspensão, reativação e remoção da configuração de sites, preservando arquivos.
- Assinatura da API clássica, HTTPS com porta personalizada, chave criptografada e rotação/pausa.
- Domínio-base e PHP por produto; snapshot de destino, marcador UUID e diretório exclusivo por provisionamento.
- Confirmação por leitura, conciliação administrativa e proteção local contra jobs duplicados; sem retry automático de mutações incertas.
- Interface esclarece que são sites gerenciados, sem compartilhar acesso administrativo do aaPanel.
- cPanel e recursos anteriores mantidos; visual base e seis assets preservados.
- Corrigida regressão na edição de produtos manuais com configuração nativa vazia, coberta por teste PHP e navegador.

## Testes locais executados

- **176 testes PHP / 792 assertions**.
- **206 verificações em Chromium:** 54 iniciais, 59 comerciais, 34 de atendimento, 31 cPanel e 28 aaPanel. Incluem navegações/paginação; não são 206 cenários independentes. Sem erros JavaScript nas cinco suites.
- **13 cenários concorrentes**, incluindo 20 entregas do mesmo job aaPanel com uma única chamada AddSite no provedor simulado.
- Instalação limpa do ZIP com dependências do lockfile: 10 migrations, zero usuários padrão, HTTP 200, versão alpha.5 e chamadas nativas desabilitadas. Rollback/reaplicação da migration nativa em banco vazio isolado.
- Lint PHP, compilação Blade, caches de configuração/rotas, Composer validate/audit e hashes dos assets.

Evidências em `docs/*RESULTS*`, `docs/CLEAN-INSTALL.txt`, `docs/PHP-LINT.txt`, `docs/DEPENDENCY-AUDIT.json` e `docs/VISUAL-ASSETS.json`.

## Limites importantes

**Nenhum aaPanel ou WHM real foi acessado.** Chamadas foram simuladas, inclusive nos testes de navegador e concorrência. Homologação externa permanece pendente.

aaPanel aqui é **site gerenciado pela equipe**, não conta de hospedagem isolada. Não cria FTP, bancos, quotas, e-mail, DNS, SSL ou login do cliente. Não disponibilizar PHP de clientes não confiáveis sem isolamento validado separadamente. Exclusão preserva arquivos; limpeza e retenção são responsabilidade operacional.

Não existe garantia distribuída de exatamente-uma-vez. Após timeout, confira workers e tarefas remotas antes de autorizar nova tentativa. Bloqueio global de chamadas não bloqueia vendas: mantenha produtos nativos indisponíveis até homologar.

**O escopo completo WHMCS + Paymenter segue em andamento.** Não há paridade total nem superioridade comprovada. A matriz agora tem 103 linhas de escopo, não 103 funcionalidades concluídas. Outros provedores, registradores, SSO, mudança de pacote, multimoeda/impostos/prorrata, importadores e antivírus continuam pendentes. Não houve homologação MySQL/SMTP, restore operacional, benchmark ou pentest externo.

## Instalar e atualizar

Leia README e `docs/OPERACAO.md`. Configuração/roteiro aaPanel: `docs/AAPANEL.md`; cPanel: `docs/CPANEL.md`. Nenhuma migration adicional foi necessária nesta rodada. Preserve a `APP_KEY`; não gere outra ao atualizar. Faça backup antes de atualizar ou habilitar remoções remotas.

ZIP somente de fonte, testes, documentação, lockfile e assets: sem `.env`, credenciais, banco de demonstração, logs ou dependências instaladas. Releases anteriores preservadas; entrega alpha.4 arquivada em `docs/HISTORICO-ALPHA4.md`.
