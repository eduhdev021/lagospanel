# Distribuição principal

Repositório: https://github.com/eduhdev021/lagospanel

O titular autorizou substituir o histórico da **main** por um único commit raiz do projeto atual, após preservar uma cópia local completa para recuperação. Publicação com force-with-lease protege contra apagar atualizações concorrentes não revisadas. A base 1.0.0 foi publicada sem marcação de prévia e com canal de Issues. Atualizações posteriores, incluindo 1.1.0, acrescentam commits normalmente; não repetem a substituição do histórico.

Backup externo ao fonte: `lagospanel-historico-antes-1.0.0.bundle`, com main anterior e tags então existentes. A cópia contém histórico completo e foi verificada com git bundle verify. Não é incorporada ao pacote nem à nova árvore Git.

Para inspecionar/recuperar localmente:

```bash
git clone --branch recovery-main /caminho/lagospanel-historico-antes-1.0.0.bundle lagospanel-recuperacao
```

**Limites da substituição:** apenas o histórico principal é substituído. Tags/releases anteriores, forks, clones, caches, links por SHA e registros de Actions não são apagados por um force-push. Tags antigas foram preservadas porque a confirmação recebida foi para a main. Isso não é um procedimento de expurgo de segredos; credenciais expostas devem ser revogadas.

Clones antigos devem preferencialmente fazer um clone novo para trabalhar na base 1.0.0; não misture automaticamente a história antiga por merge. Preserve alterações locais antes de qualquer troca.

A publicação estável não realiza deploy em servidor, importação de dados ou homologação dos provedores. Leia README, OPERACAO e ESCOPO. Nunca publique credenciais em Issues.
