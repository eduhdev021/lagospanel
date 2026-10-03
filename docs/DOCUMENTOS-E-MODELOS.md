# Documentos e modelos — alpha.6

## Faturas em PDF

Botão “Baixar fatura em PDF” nos detalhes da fatura do cliente; link PDF na lista administrativa. A rota do cliente aceita apenas o titular, inclusive quando quem acessa também é membro da equipe. A rota administrativa exige `billing.view`, além de login, conta verificada e 2FA obrigatório da equipe em produção. Downloads limitados a 10/minuto no cliente e 30/minuto na equipe. Os limites existentes de sessão/segurança continuam.

O documento contém número/estado, datas, cliente, moeda BRL, itens do snapshot, quantidade, unitário, instalação por unidade, opções, descontos e total registrado. Não recalcula o valor cobrado com os preços atuais do catálogo. O estado de pagamento é o do momento da geração. Baixar não quita fatura, não dispara provisionamento e não confirma recebimento. Canceladas/expiradas continuam disponíveis como histórico, com aviso para não pagar; consulte o portal para a situação atual.

Configure `INVOICE_ISSUER_NAME` e `INVOICE_ISSUER_DETAILS` no `.env`, depois reconstrua o cache de configuração. Nome/e-mail do cliente e identificação do emissor vêm do **cadastro/configuração atuais**, não de um snapshot fiscal imutável. Não há cadastro fiscal completo, NFS-e/NF-e, assinatura digital, cálculo de impostos nem envio automático como anexo. É um PDF de cobrança, não nota fiscal, comprovante bancário ou substituto da legislação aplicável.

Dompdf é instalado via `composer.lock`. Recursos remotos, protocolos de arquivos/URLs, PHP e JavaScript estão desativados no renderer. Template é fixo e escapa textos; usuário não fornece HTML ou CSS executável. O PDF não usa imagens externas ou links de pagamento arbitrários. Downloads forçam `attachment`, `application/pdf`, `nosniff`, CSP restritiva e `Cache-Control: no-store, private`.

Geração em memória; não há cópia de fatura publicada ou arquivada automaticamente. Runtime/font cache restritos a `storage/app/private/pdf-runtime`, fora de `public/`. Acesso auditado com ID da fatura e ator, sem registrar bytes ou conteúdo financeiro. Não é um ledger inviolável. Backup/restore e configuração do servidor ainda exigem homologação operacional.

Evidência: autorização/IDOR, permissões, validação do renderer, escape e ausência de efeitos financeiros em PHPUnit. No Chromium houve download real, leitura dos 60 itens de teste em três páginas, acentos, total, último item e renderização de todas as páginas com bibliotecas independentes. O PDF de teste contém texto de HTML malicioso como texto literal; não o executa. Isso não é auditoria externa do motor PDF.

## Respostas prontas

Acesse Atendimento → Respostas prontas. Equipe com `support.view` consulta a biblioteca; `support.manage` permite criar/editar/desativar. Título até 150 caracteres, corpo até 10.000, departamento técnico/financeiro ou todos. Biblioteca paginada em 20 registros. Seletor exibe os primeiros 200 modelos ativos em ordem de título/ID; desative modelos obsoletos para manter a biblioteca utilizável. Não existe busca avançada no seletor nesta versão.

Departamento é filtro de aplicação em chamados, **não ACL de confidencialidade**. Modelos são compartilhados pela equipe de suporte. Não colocar senhas, tokens, dados pessoais ou notas de um cliente na biblioteca. Não há placeholders interpretados, anexos de modelo, rich text ou automação por IA.

Ao clicar em “Inserir no rascunho”, o navegador consulta novamente o servidor: permissões atuais, ativação e departamento são revalidados. O texto é anexado ao rascunho, não o substitui. Limite de 10.000 caracteres também se aplica ao resultado. Se o rascunho mudar durante a consulta, a inserção é recusada para preservar a edição. Erros não apagam o texto. HTML vira texto, nunca `innerHTML`. Sem JavaScript, a resposta manual continua disponível.

**Inserir não publica resposta nem dispara notificação.** O atendente deve revisar e usar o botão existente de envio; notas internas mantêm as proteções anteriores. Alterar/desativar um modelo depois da inserção não revoga texto já copiado no rascunho. Mensagens enviadas são independentes dos modelos.

Edição usa versão sob transação/bloqueio. Uma edição desatualizada recebe HTTP 409, sem sobrescrever a versão mais recente; copie seu rascunho antes de recarregar. Auditoria registra ID, versão, estado ativo e ator, não o corpo. Modelos desativados permanecem para consulta. Não há exclusão destrutiva pela interface nem histórico de todas as versões. Rollback da migration com modelos existentes é bloqueado; faça exportação/backup antes de qualquer remoção deliberada.

## Reproduzir

- `bash scripts/test.sh`: PHPUnit e concorrência SQLite.
- Navegador usa demonstração LOCAL separada, servidor ativo e Playwright/Chromium. `python tests/browser_documents.py` também requer `pypdf` e `pymupdf`. Instale com `python -m pip install playwright pypdf pymupdf` e `python -m playwright install chromium`.
- `LAGOS_BROWSER_TESTS=1 bash scripts/test.sh` executa as seis suites de navegador. Não usar fixtures contra produção; elas criam dados fictícios. JSON de resultado é removido antes da execução nova para não manter evidência verde obsoleta.

Esses recursos não completam a lista WHMCS/Paymenter. Nenhum novo painel externo foi integrado na rodada alpha.6. O estado atualizado de Pterodactyl, VPS, registradores e demais integrações está na matriz da versão atual. Não houve contato com WHM/aaPanel/SMTP/gateways reais.
