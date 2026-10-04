(() => {
  document.querySelectorAll('[data-panel-update]').forEach((element) => {
    let count = 0;
    async function refresh() {
      if (++count > 240) { element.textContent = 'Consulta pausada após 20 minutos. Recarregue para conferir o histórico.'; return; }
      try {
        const response = await fetch(element.dataset.panelUpdate, {credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'application/json'}});
        if (response.status === 401 || response.status === 403) { element.textContent = 'Entre novamente para consultar o histórico.'; return; }
        if (response.ok) {
          const data = await response.json();
          element.textContent = 'Estado: ' + data.status + ' · Etapa: ' + data.phase;
          if (!['pending', 'running'].includes(data.status)) { location.reload(); return; }
        } else if (response.status === 503) { element.textContent = 'Painel em manutenção. Aguardando o worker finalizar.'; }
      } catch (_) { element.textContent = 'Conexão indisponível durante a atualização. A consulta será repetida.'; }
      setTimeout(refresh, 5000);
    }
    setTimeout(refresh, 5000);
  });
})();
