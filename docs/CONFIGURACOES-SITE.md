# Configurações do site — alpha.8

**ADM → Configurações do site**. Administrador principal ou equipe com `settings.view`; alterações/teste de e-mail exigem `settings.manage`. Essas permissões não concedem gestão das demais integrações.

- **Nome:** títulos e marca da aplicação; não altera os arquivos visuais originais nem substitui dados fiscais/emissor configurados separadamente para documentos.
- **URL:** origem HTTPS sem caminho/query/credenciais. Prepare domínio, DNS e TLS antes de trocar. Não configura o servidor e pode deslocar links/redirects para o novo endereço. HTTP é aceito somente em loopback nos ambientes local/testing.
- **Logo:** PNG/JPEG/WebP local, até 200 KiB e 1600×1600; SVG é recusado. Recurso servido localmente com MIME fixo/nosniff, sem URL externa. Remover retorna à imagem original.
- **Contato:** e-mail público, exibido na loja. Não configura automaticamente o remetente/SMTP.
- **Cadastro:** controla convite e bloqueia GET/POST de registro; não desativa usuários existentes, login ou recuperação.
- **E-mail:** herdar `.env`, log local ou SMTP. SMTP usa STARTTLS obrigatório (`smtp`, usualmente587) ou TLS implícito (`smtps`, usualmente465). Senha criptografada e nunca preenchida na tela: campo vazio preserva, opção de remover apaga. Cuide do APP_KEY.
- **Teste de e-mail:** somente para o próprio administrador, limitado por minuto; sem destinatário arbitrário. `log` não envia e-mail, e aceitação SMTP não prova entrega final. Não configure credenciais reais antes de homologar TLS, remetente, SPF/DKIM/DMARC e política de logs.

Edição usa versão para recusar sobrescrita desatualizada; recarregue quando houver 409. Auditoria não grava senhas/logo. A configuração é aplicada em HTTP/CLI e antes de cada job; alternar SMTP para herdar restaura a configuração original do processo e limpa transportes. Mudanças diretas no `.env`/config cache ainda exigem restart dos workers.

As chaves/modelo/pesquisa Ollama continuam em **ADM → Ollama**. Painéis, gateways, contas remotas e outros parâmetros permanecem em suas telas/documentação. Não existe importação de configuração Paymenter, instalador de extensões comerciais ou substituição da preparação operacional do servidor.
