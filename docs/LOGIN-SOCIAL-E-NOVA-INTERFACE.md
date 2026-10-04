# LagosPanel 1.5.0-dev — nova interface e login social

## Interface

- `/` agora é uma homepage pública, não um redirecionamento para a loja.
- `/loja` continua com contratação/carrinho. A homepage mostra até três produtos
  ativos, ordenados por preço; não cria preços, recursos ou promessas de SLA.
- ADM → Configurações: cartões com ícones locais, categorias, pesquisa que ignora
  acentos/pontuação, estado vazio, navegação por teclado e layouts móveis.
- ADM → Configurações → Página inicial: chamada, título, descrição e texto do botão.
  Marca, logo e contato continuam em Geral. Layout, ícones e CSS são locais;
  nenhuma biblioteca ou arquivo do WHMCS foi copiado.
- O tema base e suas cores continuam sendo do LagosPanel. Há quatro screenshots
  em `docs/screenshots/` e teste Playwright `tests/browser_experience.py`.

## Configurar login social

Somente para **clientes**. Staff/admin usa o login normal com senha e sua política
2FA; sessão iniciada por login social não permite acesso ao ADM, mesmo após promoção.
Os cinco provedores vêm **desativados**. Não existem credenciais reais na distribuição.

1. Configure domínio HTTPS correto em Geral e um remetente/SMTP funcional em E-mail.
   Novos cadastros sociais precisam confirmar o e-mail enviado pelo próprio painel.
2. Cadastre seu aplicativo em cada provedor desejado.
3. Em **Configurações → Login social**, informe Client ID e Client Secret, confirme
   sua senha administrativa e código 2FA pessoal quando cadastrado e habilite.
4. Registre exatamente a URL de retorno mostrada no formulário, incluindo HTTPS,
   hostname e caminho. Sem curinga, sem barra extra, sem confundir APP ID com Secret.
5. Teste em janela privada com **cliente**, antes de anunciar a opção publicamente.

| Provedor | Retorno para a instalação do usuário | Cadastro do app / permissões |
|---|---|---|
| GitHub | `https://lagoshost.com.br/entrar/social/github/retorno` | GitHub Settings → Developer settings → OAuth Apps. `read:user`, `user:email`. Não usar PAT. |
| X / Twitter | `https://lagoshost.com.br/entrar/social/twitter/retorno` | Portal de desenvolvedor X, User authentication settings, OAuth 2.0 Web App. `users.read`, `users.email`, `tweet.read`; Authorization Code com PKCE. A disponibilidade da API depende do plano e das permissões do app. |
| Facebook | `https://lagoshost.com.br/entrar/social/facebook/retorno` | Meta for Developers, Facebook Login para Web. `public_profile`, `email`; habilitar modo Live, atender à revisão/verificação que a Meta exigir. |
| Google | `https://lagoshost.com.br/entrar/social/google/retorno` | Google Cloud, OAuth client tipo Web Application. Configure consentimento e usuários de teste/publicação. Escopos de identidade, perfil e e-mail. |
| Microsoft | `https://lagoshost.com.br/entrar/social/microsoft/retorno` | Microsoft Entra App registrations, plataforma Web. Escolha contas organizacionais e pessoais (`common`), permissão delegada `User.Read`, `openid` e `profile`. Informe o **valor** do segredo, não seu ID; acompanhe a expiração. |

Para outros domínios, use o callback mostrado no seu ADM. Mudar o Client ID muda o
namespace das identidades: não reutilizamos vínculos de outro aplicativo. Clientes
podem entrar pela senha, desconectar o vínculo anterior e vincular o novo app.
Troca de segredo mantém vínculos existentes, mas invalida autenticações em andamento.

### Privacidade e Facebook

`/privacidade` descreve dados técnicos do login e instruções para desvinculação.
`/privacidade#exclusao` pode ser usado como URL de **instruções** para exclusão no
cadastro do app Meta. Não é um endpoint automatizado de callback de exclusão.
O operador deve revisar adequação legal, contato público e condições comerciais.
A página não afirma certificação LGPD nem aprovação Meta. Excluir a conta local é
uma solicitação ao operador; desvincular o login não apaga registros financeiros.

## Fluxos e segurança

- Redirecionamento OAuth stateful por Laravel Socialite. `state` consumido, fluxo
  por provedor e sessão, validade de 10 minutos, PKCE no Google, X e Microsoft.
- Callback por Authorization Code; nunca aceitamos JWT enviado diretamente pelo
  navegador como identidade. Microsoft valida assinatura/issuer/expiração e uma
  camada local exige audiência exata e ID token presente. Usuário vem da Graph API.
- Vinculação apenas com sessão autenticada, e-mail local verificado, senha atual e
  TOTP novo se configurado. O retorno confere identidade da sessão e fingerprint
  de senha/2FA. Não existe vínculo automático por e-mail, mesmo se dito verificado.
- Para novos clientes, o callback abre conclusão de cadastro, aceita termos,
  define senha de recuperação e envia verificação local do e-mail. Funciona também
  quando o provedor não entrega e-mail. Contas existentes entram primeiro por senha.
- Identidade composta por provedor + hash do Client ID + subject do provedor,
  com índices únicos. Uma identidade não pode pertencer a dois clientes.
- Senha e 2FA pessoal continuam disponíveis. Login social de conta migrada com
  `password_reset_required` não contorna a redefinição obrigatória.
- Tokens de acesso/refresh não são persistidos. Secret dos apps tem cast encrypted,
  não é preenchido no HTML nem enviado a logs de auditoria ou old input.
- Senha/2FA protegem também a remoção de vínculo; a senha local evita bloquear o
  cliente ao remover o último provedor. Desativar um provedor não apaga os vínculos.
- Callbacks com `no-store` e `no-referrer`; limites de tentativas e CSRF em todos
  os formulários. Não há endpoints arbitrários configuráveis para OAuth.

## Validação

Testes locais cobrem os cinco redirects, state inválido/consumido, trocas HTTP
simuladas de token/perfil (inclusive JWT Microsoft assinado com chave de fixture),
callback, conclusão sem e-mail, senha, verificação local, vínculo/desvínculo,
conflitos de identidade, clientes vs staff, exigência de 2FA, segredos e edição.
Os testes **não** equivalem a homologação com apps reais ou aprovação dos provedores.
Você ainda precisa cadastrar os aplicativos e completar um login real por provedor.

## Referências utilizadas

- https://laravel.com/docs/12.x/socialite
- https://socialiteproviders.com/Microsoft/
- https://docs.github.com/en/apps/oauth-apps/building-oauth-apps/authorizing-oauth-apps
- https://developers.google.com/identity/protocols/oauth2/web-server
- https://docs.x.com/fundamentals/authentication/oauth-2-0/authorization-code
- https://developers.facebook.com/docs/facebook-login/web/
- https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow

## Atualização manual

Use Git fast-forward e faça backup antes. Novas migrations: `000017_social_auth`
e `000018_homepage_settings`. Instale o `composer.lock` atualizado em produção com
`--no-dev --no-scripts --no-plugins`. **Antes de qualquer Artisan**, remova/preserve
os caches `bootstrap/cache/{config,packages,services}.php` antigos para não referenciar
Pail/ferramentas de desenvolvimento ausentes. Gere novamente `package:discover`,
migrations e caches como usuário da aplicação. Arquivos de código novos precisam
ser legíveis por PHP-FPM: não use umask 077 para o checkout, somente para backups.
Não substitua `.env`, não regenere APP_KEY e não publique banco ou credenciais.


### Reproduzir a demonstração local

Não rode a fixture no servidor de produção. Ela exige APP_ENV=local e o caminho
exato de banco abaixo, cria apenas contas/planos fictícios e não chama provedores.
Use um APP_KEY de desenvolvimento e nenhuma credencial real no ambiente.

```sh
export APP_ENV=local APP_URL=http://127.0.0.1:8080
export DB_CONNECTION=sqlite DB_DATABASE="$PWD/.cache/experience.sqlite"
export MAIL_MAILER=log SESSION_DRIVER=file CACHE_STORE=file
php tests/experience-fixture.php
php artisan serve --host=127.0.0.1 --port=8080
# Em outro terminal com as dependências Playwright instaladas:
python tests/browser_experience.py
```

As senhas/identidades `@experience.invalid` da fixture são exclusivamente de teste.
