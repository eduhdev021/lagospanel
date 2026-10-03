# Pterodactyl — Application API (1.2.0)

## O que está implementado

Provisionamento de servidores de jogos após pagamento, recursos por plano, consulta, suspensão, reativação e remoção. Confirmação por leitura e conciliação de estados incertos, além de acompanhamento de instalação em fila. **Não houve teste em Pterodactyl/Wings real.** Compatibilidade com uma versão específica deve ser homologada pelo operador.

O plano exige conta vinculada ou a opção `"auto_account":true`. Com ela, preparação da conta pela fila somente após pagamento, sem HTTP no checkout. Há ligar/parar/reiniciar com Client API Key não administrativa do cliente, fornecida por solicitação e não persistida. Application Key permanece privada. Sem console, SSO Pterodactyl, SFTP, reinstalação, backups ou troca de plano dentro do LagosPanel. Veja [automação e acesso](AUTOMACAO-E-ACESSO-1.2.md) e [contas](PTERODACTYL-CONTAS.md).

## Configurar

1. Prepare Pterodactyl, Wings, localização, nós, alocações livres, egg, imagem e variáveis. Faça backup e use recursos de homologação descartáveis. Mantenha produto indisponível na loja até concluir os testes.
2. Gere uma **Application API Key**, não Client API Key. Exija apenas leitura de usuários e leitura/alteração de servidores necessárias aos endpoints utilizados; confirme as ACLs na versão instalada. Restrinja IPs/tráfego no firewall/proxy. TLS válido é obrigatório, incluindo em portas próprias. O destino deve ser uma origem, sem `/api`, usuário, query ou fragmento.
3. ADM → Integrações: escolha Pterodactyl, origem HTTPS e chave. Confirme chamadas e habilite a integração. Chave fica criptografada com `APP_KEY`; a rotação não altera endpoint/identidade dos serviços existentes.
4. Em **Contas Pterodactyl**, crie a conta remota pelo novo formulário ou, para uma conta existente, informe ID do cliente LagosPanel e ID do usuário Pterodactyl. Confira que pertencem à mesma pessoa. O e-mail remoto deve ser igual ao email do cliente capturado no pedido, e `root_admin` remoto deve ser falso. São conferidos antes de cada observação/mutação. A tela salva o vínculo, mas não o valida remotamente naquele momento.
5. O vínculo é único por integração/cliente e integração/ID remoto; não pode ser sobrescrito pela tela. Mudanças de identidade exigem planejamento operacional, sem adulterar snapshots. Equipe precisa de `integrations.view/manage` conforme ação e `customers.view` para consultar/vincular contas.
6. Em Produto, escolha a integração e preencha o JSON de plano. Exemplo (ajuste ao seu egg, não é configuração universal):

```json
{
  "egg": 1,
  "location": 1,
  "docker_image": "ghcr.io/pterodactyl/yolks:java_21",
  "startup": "java -Xms128M -Xmx1024M -jar server.jar",
  "environment": {
    "SERVER_JARFILE": "server.jar",
    "MINECRAFT_VERSION": "latest",
    "BUILD_NUMBER": "latest"
  },
  "memory": 1024,
  "disk": 10240,
  "cpu": 100,
  "swap": 0,
  "io": 500,
  "databases": 0,
  "allocations": 1,
  "backups": 1
}
```

Memória/disco/swap em MiB; CPU em percentual (100 = um núcleo lógico), IO conforme Pterodactyl. Memória/disco/CPU sem zero ilimitado nesta versão. `environment` aceita até 50 variáveis, valores de texto/null e nomes em maiúsculas. Limites/variáveis e startup precisam ser compatíveis com o egg e a imagem. Se aumentar memória no plano, ajuste também o comando JVM quando ele usar valor fixo. **Não coloque segredos no plano:** campos de configuração ficam no JSON de produto/snapshot, não são cofre de senhas.

Deploy pede a localização escolhida e alocação automática do Pterodactyl. Não faz agendamento de capacidade próprio, descoberta de eggs ou escolha de porta pelo cliente. Falta de alocação/capacidade exige intervenção. Limites de databases/backups/allocations são limites permitidos, não criação automática desses recursos.

7. Contratação de cliente sem vínculo é bloqueada antes de concluir cobrança/reserva. Opções configuráveis selecionadas também são bloqueadas até existir mapeamento; use produtos separados por configuração.
8. Libere `NATIVE_PROVISIONING_ENABLED=true` somente após preparar o ambiente. Recarregue config/reinicie workers. **O bloqueio global impede chamadas, não vendas**. Mantenha worker persistente e supervisão conforme `OPERACAO.md`.

## Contrato e segurança

- Bearer no header e Accept da Application API; HTTPS verificado, sem redirects, sem retries de mutações. Connect timeout 3s/HTTP 8s, resposta limitada a 1 MiB.
- Snapshot imutável de endpoint, egg, recursos, imagem, configuração, proprietário/e-mail e UUID externo exclusivo `lagos-...` por serviço.
- `GET /api/application/users/{id}` confirma titular não administrador; `GET /api/application/servers/external/{external_id}` confirma identidade e recursos. Só 404 JSON com `NotFoundHttpException`, após validar titular, é considerado ausência; HTML de proxy ou erro genérico não comprova ausência.
- `POST /api/application/servers`: espera 201, identidade retornada e leitura posterior com o mesmo ID. Envia `deploy.locations`, limites, variáveis e `start_on_completion=true`, sem `skip_scripts`/`oom_disabled` habilitados.
- `POST /servers/{id}/suspend`, `POST /servers/{id}/unsuspend`, `DELETE /servers/{id}`: esperam 204 e leitura posterior. **Não usa force delete.** Remoção é destrutiva e exige confirmação administrativa. Backups/retencão e efeitos em Wings precisam ser homologados.
- Observação confere ID externo/remoto, owner, egg, imagem, limites, feature limits e estado. Respostas brutas, variáveis remotas e chaves não entram em logs de operação/auditoria. Mutação não é autorizada com divergência.
- `active` local significa provisionamento instalado/não suspenso, **não comprova processo de jogo rodando, rede aberta ou Wings saudável**.

## Instalação assíncrona e revisão

Se após criar o servidor a leitura mostrar `installing`, o serviço permanece pendente e a operação fica em revisão. Uma consulta em fila acompanha até 20 vezes, com atraso de 30s mais tempo de fila/HTTP. Consultas não fazem novas criações. Ao observar estado ativo com identidade correta e pagamento registrado, o serviço é ativado. Falha de instalação, erro de consulta, pausa, mudança de identidade ou esgotamento das consultas mantêm revisão para operador.

Consultas usam identificação de execução: jobs duplicados/substituídos não finalizam execuções novas. A conciliação administrativa manual continua disponível. Ela substitui a execução anterior e interrompe o acompanhamento automático antigo, inclusive ao escolher somente consultar; se consultar durante a instalação, confirme manualmente após ela terminar. Não execute retries indiscriminados: timeout pode ocorrer após criação remota, e leitura de ausência não garante que uma requisição antiga terminou. Confira worker/tarefa remota antes de autorizar reenvio. Não há garantia distribuída de exatamente-uma-vez.

## Homologação real obrigatória (pendente)

- Registrar versões de Panel/Wings, ACLs, certificado, rede, egg, imagem, variáveis e termos/licenças do jogo.
- Validar chave inválida/rotacionada, pausa, IP recusado, TLS inválido e ausência de segredos em logs externos.
- Vincular conta não administradora correta; tentar conta administrativa, email divergente e vínculo repetido (recusados).
- Criar após pagamento, comprovar recursos efetivos, alocação/localização e instalação; confirmar acesso apenas pelo titular.
- Exercitar falta de capacidade, falha/instalação demorada, indisponibilidade Wings e consulta automática esgotada.
- Suspender/reativar, conciliar timeout e simular jobs duplicados; conferir que não há nova criação durante instalação.
- Remover recurso de teste com backup; conferir arquivos/bancos/backups no provedor e que a conta do cliente não foi removida.
- Validar concorrência/capacidade, MySQL e restore operacional separadamente.

## Fontes de contrato

Consultadas em 03/10/2026, branch pública `1.0-develop` (não é certificação de uma release):
- https://raw.githubusercontent.com/pterodactyl/panel/1.0-develop/routes/api-application.php
- https://raw.githubusercontent.com/pterodactyl/panel/1.0-develop/app/Http/Requests/Api/Application/Servers/StoreServerRequest.php
- https://raw.githubusercontent.com/pterodactyl/panel/1.0-develop/app/Transformers/Api/Application/ServerTransformer.php
- https://raw.githubusercontent.com/pterodactyl/panel/1.0-develop/app/Transformers/Api/Application/UserTransformer.php

Evidências locais: `PterodactylTest.php`, `BROWSER-PTERO-AI-RESULTS.json`, `CONCURRENCY-RESULTS.json`. Todos os transportes de provedor desses testes foram simulados.
