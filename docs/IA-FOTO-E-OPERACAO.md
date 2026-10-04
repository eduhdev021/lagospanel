# Foto de perfil e diagnóstico de IA

## Foto
Em **Meu perfil**, autorize **Buscar / atualizar Gravatar**. A consulta usa o hash SHA-256 do e-mail desta conta. Não ocorre consulta automática ao carregar páginas. Uma cópia fica armazenada localmente e é servida somente ao próprio usuário autenticado. O Gravatar precisa ter uma foto pública de classificação geral vinculada ao mesmo e-mail. Se não funcionar, envie PNG/JPG/WebP de até 256 KiB e 1024 × 1024 pixels. Importar novamente atualiza a cópia. Remover volta às iniciais.

## Chave vazia não significa chave ausente
O ADM mostra **Chave salva** quando há credencial. O segredo é criptografado e não volta ao HTML. Campo vazio preserva o valor; o botão Mostrar digitado revela apenas o que acabou de ser digitado. A remoção é explícita.

Salve a origem e a chave com o chat desativado, consulte o catálogo, selecione um modelo e habilite o chat. O teste de geração exige confirmação de senha (2FA somente para quem ativou voluntariamente), autorização da chamada e pode consumir cota. O teste usa uma frase fixa, não conversas reais. Uma resposta bem-sucedida confirma o provedor, não a fila. Ajuste timeout de PHP-FPM/proxy para permitir pelo menos a duração da chamada (45 segundos mais overhead).

## Fila permanente
O chat depende da conexão **database**, mesmo quando a conexão padrão da instalação é outra. Depois de atualizar código/dependências e aplicar migrations, execute no diretório `/var/www/lagospanel`, com o PHP da instalação:

```sh
sudo -u www-data /usr/bin/php8.4 artisan lagos:ai-status
```

O relatório é local, sem chamada ao provedor, sem tokens nem conteúdo das conversas. Sinal recente significa que um worker atualizado percorreu o loop da fila nos últimos 120 segundos; não garante sucesso de cada tarefa. Sem sinal também pode significar worker antigo ainda não reiniciado. O sinal se refere à fila database configurada, não a qualquer worker no servidor.

**Primeiro confira o supervisor/systemd/aaPanel existente; não crie dois gerenciadores para o mesmo worker.** Reinicie o worker LagosPanel pelo gerenciador existente depois de atualizar. Não pare serviços de outros sites.

Se não existir nenhum worker LagosPanel, este é um exemplo de unidade systemd dedicada (adapte o PHP e a fila ao relatório):

```ini
[Unit]
Description=LagosPanel queue worker
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/lagospanel
ExecStart=/usr/bin/php8.4 /var/www/lagospanel/artisan queue:work database --queue=default --sleep=3 --tries=1 --timeout=80
Restart=always
RestartSec=5
TimeoutStopSec=100

[Install]
WantedBy=multi-user.target
```

Salve em `/etc/systemd/system/lagospanel-worker.service` somente após conferir a inexistência de outro gerenciador. O `retry_after` precisa ser maior que o timeout de 80 segundos (padrão: 90). Ajuste `--queue` se `DB_QUEUE` não for `default`. Ative com `systemctl daemon-reload` e `systemctl enable --now lagospanel-worker`. Logs: `journalctl -u lagospanel-worker -n 80 --no-pager`. Não publique logs sem revisar dados sensíveis. Mantenha o scheduler existente; worker não o substitui.

## Motivos seguros
O chat distingue chave recusada (401), operação sem permissão (403), API/modelo não encontrado (404), limite/cota (429), indisponibilidade, conexão e resposta inválida. Não exibe corpos de erro do provedor, chaves nem URLs com credenciais. Mensagens abandonadas expiram em cinco minutos e não são reenviadas automaticamente. A causa definitiva de uma falha no servidor exige o diagnóstico dessa instalação.

## Escopo e validação
Essas mudanças não equivalem a paridade integral com WHMCS/Paymenter. Não foi incorporado código proprietário do WHMCS nem código Paymenter nesta alteração. Testes HTTP usam respostas simuladas; não comprovam conectividade, cota ou modelo reais. Nenhuma chamada paga é feita ao abrir a configuração.
