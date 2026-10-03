# Entrega — LagosPanel independente 1.0.0-alpha.4

## Implementação desta rodada

- Driver nativo cPanel/WHM API 1, separado do conector JSON: criação, consulta, suspensão, reativação e remoção.
- Pacote/domínio-base por produto, identidade estável e snapshot de provisionamento por serviço.
- Criação/reativação condicionada a pagamento; confirmação do resultado por consulta posterior.
- Conciliação administrativa de resultados incertos: consultar, confirmar ou autorizar uma tentativa, com permissões, justificativa e confirmação explícita.
- Proteções contra job duplicado, referência reaproveitada para outro serviço, operação conflitante e conclusão de execução substituída.
- Token criptografado, rotação/pausa de integração, senha inicial criptografada e revelação ao titular com senha atual e TOTP novo quando habilitado.
- Confirmação adicional para exclusão remota, rollback bloqueado com vínculos nativos e bloqueio de venda de opções sem mapeamento WHM.

O visual base e os assets foram preservados. Novos campos usam os componentes existentes. Não houve importação nem alteração de dados do sistema anterior.

## Testes locais executados

- **159 testes PHP / 727 assertions** — 125 anteriores e 34 novos.
- **175 verificações em Chromium:** 54 dos fluxos iniciais, 57 da expansão comercial, 34 do atendimento e 30 da integração nativa. A contagem inclui navegações/paginação, não 175 cenários independentes.
- **12 cenários concorrentes**, incluindo 20 entregas do mesmo job com uma criação no WHM simulado e 20 solicitações/20 entregas de suspensão com uma mutação simulada.
- Instalação limpa do ZIP, 10 migrations, zero usuários/senhas padrão; roundtrip da migration nativa em banco vazio.
- Lint PHP/Blade, Composer validate/audit e checksums dos assets preservados.

Resultados em `docs/PHPUNIT-RESULTS.txt`, `docs/BROWSER-*-RESULTS.json`, `docs/CONCURRENCY-RESULTS.json`, `docs/CLEAN-INSTALL.txt`, `docs/PHP-LINT.txt`, `docs/DEPENDENCY-AUDIT.json` e `docs/VISUAL-ASSETS.json`.

## Limites da evidência

**Não houve acesso a WHM real.** HTTP foi interceptado por testes, inclusive no fluxo de navegador. A demonstração usa dados fictícios e chamadas nativas ficam bloqueadas por padrão. O módulo é implementado, mas a homologação externa permanece pendente. Leituras remotas não oferecem transação distribuída nem garantia de exatamente-uma-vez; reenvio após timeout exige conferência humana no worker e no WHM.

Não foram implementados nesta rodada SSO, mudança de pacote, domínio próprio no checkout, outros painéis/provedores, domínios/registradores, multimoeda/impostos/prorrata, importadores, antivírus ou restauração completa. **O escopo completo WHMCS + Paymenter continua em andamento; não há paridade total nem prova de superioridade.** A matriz mantém 102 linhas de escopo com estados explícitos.

Não houve teste de produção, homologação MySQL/MariaDB/SMTP, benchmark comparativo, pentest externo nem execução de CI/push remoto.

## Instalar e atualizar

O ZIP contém código, testes, migrations, documentação, lockfile e assets. Não contém `.env`, runtime, credenciais da demonstração, banco, e-mails, logs ou dependências instaladas. Versões anteriores foram preservadas.

Instalação: README. Atualização: `docs/OPERACAO.md`. Configuração e roteiro de homologação: `docs/CPANEL.md`. Guarde a `APP_KEY`; não gere outra ao atualizar. Não habilite exclusões remotas sem backup do provedor.
