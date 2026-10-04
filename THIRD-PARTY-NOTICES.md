# Proveniência e licenças

- **LagosPanel original:** `public/assets/panel.css`, `panel.js` e imagens foram preservados sem alteração a partir de `lagospanel/resources/assets`. O helper SVG foi adaptado para escape do novo framework. A marca/layout pertencem ao projeto fornecido pelo usuário. A declaração original foi preservada em `licenses/LagosPanel-original.md`.
- A origem contém declarações conflitantes: licença proprietária no documento geral e GPL-2.0-or-later no cabeçalho do tema. Esta entrega não resolve esse conflito nem concede direitos sobre ativos de terceiros. O titular deve revisar as condições antes de redistribuir.
- **Laravel:** esqueleto `laravel/laravel v12.12.2`, framework resolvido `12.69.3`. Licença MIT em `licenses/Laravel-MIT.txt`. Versões exatas das dependências em `composer.lock`; licenças de cada pacote permanecem em sua distribuição.
- **Paymenter:** referência arquitetural autorizada pelo usuário, commit `4bc582bb0ef9973e6b534f0a9248341d2b6c79da`. Não é dependência de execução desta aplicação; nenhum template ou ativo visual seu foi incorporado. A implementação de domínio desta versão foi escrita separadamente. Aviso/licença MIT preservado em `licenses/Paymenter-MIT.txt`.
- Nenhum pacote do núcleo anterior integra o novo runtime. Os arquivos de referência anteriores permanecem separados.
- A classificação `proprietary` no manifesto identifica o aplicativo do titular, não relicencia Laravel, referências ou dependências.

- **Dompdf:** geração de PDF, biblioteca não modificada, licença LGPL-2.1; cópia em `licenses/Dompdf-LGPL-2.1.txt`. Dependências e versões em `composer.lock`, fontes/licenças preservadas nas distribuições instaladas pelo Composer. O ZIP de fonte não embute vendor nem relicencia esses componentes. Projeto: https://github.com/dompdf/dompdf.

- **Pterodactyl / Ollama:** contratos de API usados como referência, sem incorporar os respectivos núcleos, modelos de IA ou assets ao runtime. Fontes/rotas de referência constam em `docs/PTERODACTYL.md` e `docs/OLLAMA.md`. O operador deve observar licenças dos jogos/eggs/imagens/modelos e termos do provedor.
- **qrcode-generator:** `public/assets/qrcode-generator.js`, copyright (c) 2009 Kazuhiko Arase, licença MIT. Usado somente para gerar o SVG do QR Code Pix no navegador. Fonte: https://github.com/kazuhikoarase/qrcode-generator. A expressão QR Code é marca registrada da DENSO WAVE INCORPORATED.
