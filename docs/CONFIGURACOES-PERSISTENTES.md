# Formulários: salvar, validar e manter os valores

## Comportamento
- Campos comuns (nome, URL, Client ID, host SMTP, porta, usuário, remetente, prazos e opções) continuam visíveis após salvar.
- Segredos não são devolvidos ao navegador. Um campo de senha/token vazio mantém o segredo existente; remoção exige a opção explícita correspondente.
- Em erros de validação, os formulários operacionais preservam apenas campos conhecidos não secretos. Senha de confirmação, tokens, segredos e campos desconhecidos não são guardados no rascunho.
- O rascunho de login social pertence apenas ao provedor editado. O Client ID não desaparece e não é replicado para outros provedores.
- Opções desmarcadas de cadastro e IA permanecem desmarcadas quando a validação falha.
- Se a validação do navegador impedir Salvar, um resumo informa quais campos faltam. Campos dentro de seções recolhidas são revelados. Isso não remove validação no servidor, senha de confirmação, permissões nem o controle de versão concorrente.
- JS e CSS locais usam caminhos da mesma origem com hash do conteúdo. Uma atualização do arquivo muda a URL de cache mesmo que a versão comercial do painel seja a mesma.

## Testes realizados
- Suite PHP completa: 547 testes, 3172 verificações.
- Navegador: 44 verificações específicas de navegação/formulários, incluindo 22 destinos da central, persistência após reload, validação no cliente e servidor e sigilo dos segredos. Mais 23 verificações de interface e 30 do chat/configurações existentes, todas aprovadas.
- Compilação de views e cache de rotas aprovados.
- Dados locais de demonstração; nenhum teste contra SMTP, OAuth ou modelo real e nenhuma alteração no servidor do cliente.

Estes testes não atestam que toda a instalação de produção esteja corrigida. Uma falha restante requer a página e a mensagem exibida (sem credenciais). O worker confirmado no servidor não deve ser reinstalado por causa dessas alterações de formulário.
