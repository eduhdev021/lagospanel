# LagosPanel — desenvolvimento consolidado na main

**1.4.0-dev. Não é uma nova release estável nem a conclusão de toda a paridade solicitada.**

A base contém clientes Plesk isolados/SSO, orçamentos avulsos, avisos/incidentes/manutenções e downloads privados, além dos recursos anteriores. O escopo e os limites estão em [DESENVOLVIMENTO.md](docs/DESENVOLVIMENTO.md) e [COMERCIAL-E-CONTEUDO.md](docs/COMERCIAL-E-CONTEUDO.md).

A release estável 1.3.0 e seus arquivos/checksums permanecem inalterados. Seu relatório histórico está em [ENTREGA-1.3.0.md](docs/ENTREGA-1.3.0.md).

Aplicar código não é fazer deploy. Faça backup do banco, APP_KEY e arquivos privados, instale as dependências do lockfile, aplique as migrations pendentes, refaça caches e reinicie workers. Há duas migrations novas em relação à 1.3.0 (17 no total); rollback que apagaria novos registros é bloqueado. Não regenere APP_KEY.

Resultados atuais: `docs/PHPUNIT-RESULTS.txt`, `docs/CONCURRENCY-RESULTS.json` e `docs/BROWSER-COMMERCIAL-RESULTS.json`. Os testes não certificam provedores autenticados, pagamentos reais, infraestrutura MySQL ou disponibilidade operacional.
