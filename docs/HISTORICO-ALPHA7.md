# Entrega — LagosPanel independente 1.0.0-alpha.7

## Pterodactyl

- Driver nativo da Application API: criar, consultar, suspender, reativar e remover servidores.
- Plano por produto com egg, localização, imagem, startup, variáveis e limites de memória/disco/CPU/IO e recursos adicionais.
- Vínculo administrativo explícito entre cliente local e usuário Pterodactyl existente; valida titular/e-mail e recusa usuário remoto administrador.
- Snapshots por serviço, UUID externo, confirmação do ID criado por leitura, proteção contra entrega duplicada de jobs e conciliação de resultados incertos.
- Instalação assíncrona acompanhada por consultas em fila; não ativa prematuramente nem repete criação enquanto instala.
- Exclusão exige confirmação e não usa force delete. Cliente tem link para o painel, sem receber Application Key.

**Recorte:** não cria usuários remotos automaticamente, não faz SSO, console/energia/reinstalação/troca de plano no LagosPanel. É necessário cadastrar a conta no Pterodactyl e vinculá-la antes da contratação. cPanel/aaPanel anteriores foram mantidos; outros drivers não foram adicionados nesta rodada.

## Chat de suporte com Ollama

- ADM → Integrações → Configurar chat Ollama e modelos: origem, chave criptografada/oculta, consulta de catálogo e seleção de modelo no catálogo retornado.
- Troca de URL/chave limpa catálogo/seleção; habilitação exige confirmação. Permissões administrativas e versão de configuração protegem alterações concorrentes.
- Chat privado do cliente com ciência do envio ao provedor, mensagens criptografadas em repouso e respostas assíncronas por fila.
- Isolamento entre titulares/conversas, cotas diárias, uma solicitação pendente por usuário e idempotência de envio/job.
- Texto seguro na interface, sem execução de HTML, ferramentas, acesso a senhas, comandos ou consulta automática da conta.
- Link para chamado humano e exclusão local da conversa; pausa/alteração/exclusão bloqueiam publicação tardia.

**Recorte:** a IA pode errar; não é atendente humano nem executa ações. Catálogo consultado com a chave não garante cota/direito de inferência. Exclusão local não apaga retenção do provedor/backups e não cancela requisição já enviada. Não há streaming, anexos, RAG, orçamento global ou filas dedicadas por recurso.

O visual base foi mantido; seis assets originais preservados por hash. Novas telas usam os componentes existentes e JavaScript separado.

## Testes executados

- **229 testes PHP / 1.006 assertions**, incluindo 18 testes Pterodactyl e 22 Ollama.
- **275 verificações em Chromium:** 54 base, 62 comércio, 34 suporte, 32 cPanel, 29 aaPanel, 28 documentos/modelos e 36 Pterodactyl/Ollama. Incluem navegação/paginação, não são 275 cenários independentes. Sete suites sem erros JavaScript.
- **16 cenários concorrentes**, incluindo 20 jobs Pterodactyl com uma criação, 20 envios da mesma mensagem com uma cota consumida e 20 jobs de IA com uma chamada Ollama simulada.
- Instalação limpa pelo ZIP/lockfile, sem vendor pré-copiado: **12 migrations**, zero usuários padrão, zero configurações IA/vínculos Pterodactyl, HTTP 200, versão alpha.7 e chamadas nativas bloqueadas por padrão.
- Rollback/reaplicação da migration nova em banco vazio, caches de rotas/configuração/Blade e PDF de regressão gerado na instalação limpa.
- Lint PHP, Pint, Composer validate/audit sem advisories e hashes dos assets.

Evidências em `docs/*RESULTS*`, `docs/CLEAN-INSTALL.txt`, `docs/PHP-LINT.txt`, `docs/DEPENDENCY-AUDIT.json` e `docs/VISUAL-ASSETS.json`.

## Limites da evidência

**Nenhum servidor Pterodactyl/Wings ou Ollama real foi acessado.** Transportes foram simulados. No navegador, a consulta de catálogo é interceptada pelo harness e delegada ao controlador local com Http::fake; a rota completa/permissões são testadas no PHPUnit. A escolha do modelo e persistência da configuração passam pela interface real. Provisionamento/inferência usam os jobs reais com transporte interceptado. Isso não homologa credenciais, ACLs, modelos, jogos, rede, qualidade de resposta ou capacidade real.

Não houve homologação MySQL/MariaDB/SMTP, restore operacional, benchmark comparativo, pentest externo ou CI remoto. Não há garantia distribuída de exatamente-uma-vez. O escopo completo WHMCS/Paymenter continua em andamento; matriz com 104 linhas não equivale a 104 funcionalidades concluídas.

## Configurar e atualizar

- **Pterodactyl:** `docs/PTERODACTYL.md` — Application Key, contas, plano, fila, bloqueio global e roteiro real.
- **Ollama:** `docs/OLLAMA.md` — chave/modelos no ADM, cloud/servidor próprio, privacidade, cotas e worker.
- **Atualização:** README e `docs/OPERACAO.md`. Faça backup, preserve `APP_KEY`, instale o lockfile, aplique migrations e reconstrua caches/reinicie workers. Não gere outra chave em atualização.

ZIP de fonte/testes/documentação/assets, sem `.env`, banco, credenciais reais ou de demonstração, runtime, logs e dependências instaladas. Releases anteriores preservadas; alpha.6 arquivada em `docs/HISTORICO-ALPHA6.md`.
