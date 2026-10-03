# Entrega — LagosPanel independente 1.0.0-alpha.6

## Implementado nesta rodada

- Faturas em PDF para o titular e equipe com permissão financeira, com itens/valores do snapshot, instalação, descontos, acentos e paginação.
- Download privado/auditado, sem alterar estado financeiro e sem expor arquivos por URL pública. Renderer sem recursos remotos, PHP, JavaScript ou protocolos de arquivos/URLs.
- Biblioteca de respostas prontas: criar, editar, desativar, departamento de uso e permissões de leitura/alteração.
- Controle de versão impede sobrescrever edição mais recente; rollback com modelos existentes é bloqueado.
- Inserção de modelo no rascunho preserva texto existente, revalida disponibilidade no servidor e não envia automaticamente. HTML é texto, não executável.
- Visual original mantido, incluindo hashes dos seis assets-base. Novo JavaScript separado, sem alterar o CSS/JS original.

## Testes executados

- **189 testes PHP / 861 assertions**, incluindo 13 novos testes de documentos/modelos.
- **234 verificações em Chromium:** 54 base, 59 comércio, 34 suporte, 31 cPanel, 28 aaPanel e 28 documentos/modelos. Incluem navegação/paginação, não são 234 cenários independentes. Seis suites sem erros JavaScript.
- PDF baixado no navegador, 60 itens em três páginas; conteúdo extraído e páginas renderizadas por bibliotecas independentes. Verificados acentos, total, último item, texto malicioso escapado e acesso de outro titular negado.
- **13 cenários concorrentes** anteriores reexecutados, incluindo saldo/estoque/anexos e duplicações de jobs WHM/aaPanel simulados. Edição desatualizada de modelos coberta por teste de conflito 409; não há novo benchmark concorrente de PDF/modelos.
- Instalação limpa do ZIP, Composer pelo lockfile sem vendor pré-copiado; **11 migrations**, zero usuários padrão, HTTP 200, versão alpha.6 e chamadas nativas desabilitadas. PDF gerado também na instalação limpa, com dados efêmeros em memória.
- Roundtrip da nova migration em banco vazio, caches de rotas/configuração, Blade, lint PHP, Pint, Composer validate/audit sem advisories e hashes de assets.

Evidências em `docs/*RESULTS*`, `docs/CLEAN-INSTALL.txt`, `docs/PHP-LINT.txt`, `docs/DEPENDENCY-AUDIT.json` e `docs/VISUAL-ASSETS.json`.

## Limites e trabalho restante

**A lista completa solicitada não está concluída.** Nesta rodada foram entregues documentos de cobrança em PDF e respostas prontas, não novos drivers externos nem paridade completa WHMCS/Paymenter. A matriz acompanha 104 linhas de escopo, não 104 funcionalidades prontas. Plesk/DirectAdmin, jogos, VPS/cloud, registradores, upgrades, multimoeda/impostos/prorrata, estornos, importadores e demais expansões permanecem pendentes.

PDF é **não fiscal**, sem assinatura, impostos ou NFS-e/NF-e. Identificação do cliente/emissor usa cadastro/configuração atual; não é snapshot fiscal imutável. Biblioteca não tem ACL por departamento, placeholders, anexos próprios, busca avançada ou histórico completo de versões. Inserção não é envio automatizado; exige revisão do atendente.

Não houve acesso a WHM/aaPanel, SMTP ou gateways reais. Os testes anteriores de provedores continuam simulados. Não houve homologação MySQL/MariaDB, restore operacional, benchmark comparativo, pentest externo nem execução remota de CI. As ressalvas de cPanel/aaPanel das entregas anteriores continuam válidas.

## Instalar/atualizar

README e `docs/OPERACAO.md`. Faça backup de banco e APP_KEY; reinstale dependências via Composer, aplique migrations e reconstrua caches/reinicie workers. **Não gere outra APP_KEY ao atualizar.** Instruções e limites dos novos recursos: `docs/DOCUMENTOS-E-MODELOS.md`.

O ZIP contém fontes, testes, documentação, lockfile e assets; não inclui dependências instaladas, credenciais, banco, `.env`, PDF de cliente, logs ou runtime. Releases anteriores preservadas; alpha.5 arquivada em `docs/HISTORICO-ALPHA5.md`.
