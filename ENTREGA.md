# LagosPanel — 1.0.0

## Implementado nesta etapa

**Pesquisa na internet pelo harness:** a aplicação pesquisa via Ollama Web Search e entrega trechos/fontes ao modelo; não exige tool calling nativo. Chave de pesquisa no ADM, consulta pública e consentimento por mensagem, fontes privadas por titular, limites e falhas explícitas. Sem navegação autônoma ou ações administrativas da IA.

**Instalador pelo navegador:** chave temporária preparada pelo operador, requisitos, SQLite/MySQL, primeiro administrador, banco vazio, proteção de APP_KEY, retomada de tentativa parcial e bloqueio após conclusão. Requer servidor/domínio/HTTPS preparados.

**Configuração pelo ADM:** nome, URL, logo, contato, cadastro e SMTP com senha criptografada/teste de e-mail, permissões e controle de versão. Mantidos os seis assets visuais originais, sem troca de tema.

**Publicação 1.0.0:** nova base principal em `eduhdev021/lagospanel`, com um único commit raiz na main, conforme autorização do titular. Backup completo anterior preservado separadamente. Tags anteriores não foram autorizadas para remoção. Problemas podem ser relatados por Issues. Detalhes em `docs/GITHUB.md`.

## Verificação local

| Verificação | Resultado |
|---|---|
| PHPUnit | 249 testes, 1100 asserções |
| Chromium | 292 verificações em nove suítes, inclusive 15 do instalador |
| Concorrência | 16 cenários, até 20 processos |
| Guardas adicionais do instalador | 6 casos em SQLite real isolado |
| Composer | Manifesto validado; audit sem advisories/abandonados no momento da execução |
| Visual | Seis hashes originais preservados |

Relatórios em `docs/*RESULTS*`, `COMPOSER-AUDIT.json`, `VISUAL-ASSETS.json`. Busca, inferência, gateways e painéis remotos foram simulados. O instalador foi exercitado com SQLite real; e-mail apenas em modo log/fakes. Não há homologação de MySQL/MariaDB, SMTP externo, Ollama Cloud, Pterodactyl/Wings ou demais provedores reais, nem auditoria externa/carga sustentada/restore de produção.

Durante o desenvolvimento, um teste de instalador mal isolado afetou a configuração da demonstração fictícia local. A regressão foi corrigida e coberta por teste; o ambiente de demonstração foi recriado em banco separado, sem sobrescrever o anterior. Os testes atuais isolam `.env`, chave de instalação, locks e bancos em diretórios temporários. Nenhum banco de usuário externo foi conectado.

## Instalar e operar

Leia `README.md`, `docs/INSTALACAO-WEB.md`, `docs/CONFIGURACOES-SITE.md` e `docs/OLLAMA.md`. ZIP é **fonte**, sem credenciais/dados/vendor; Composer instala as dependências do lockfile. Instalação nova não cria conta/senha padrão. Para atualizar a versão independente, backup + migrations + restart dos workers; nunca recriar APP_KEY nem usar o assistente sobre dados existentes.

## O que não está concluído

Publicada como 1.0.0 por decisão do titular; isso não estabelece paridade total com WHMCS/Paymenter nem homologação de produção. Faltam Plesk/DirectAdmin, VPS/cloud, registradores, SSO/upgrades, financeiro avançado/multimoeda/impostos/prorrata/estornos, automações avançadas de suporte, importadores e homologação operacional. Consulte `docs/ESCOPO.md` e a matriz de 104 linhas. Esta etapa não importa os dados do sistema anterior nem habilita integrações reais automaticamente.
