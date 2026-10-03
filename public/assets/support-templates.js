(function () {
  'use strict';
  document.querySelectorAll('[data-canned-reply]').forEach(function (box) {
    var button = box.querySelector('[data-canned-insert]');
    button.addEventListener('click', async function () {
      var url = box.querySelector('select').value;
      var status = box.querySelector('[data-canned-status]');
      var body = box.closest('form').querySelector('textarea[name="body"]');
      if (!url) { status.textContent = 'Selecione um modelo.'; return; }
      var before = body.value;
      button.disabled = true;
      try {
        var response = await fetch(url, {credentials: 'same-origin', headers: {'Accept': 'application/json'}});
        if (!response.ok) throw new Error('Modelo indisponível ou acesso expirado. Recarregue a página.');
        var data = await response.json();
        if (typeof data.body !== 'string') throw new Error('Resposta inválida.');
        if (body.value !== before) throw new Error('O rascunho mudou durante a consulta. Clique novamente para inserir.');
        var text = before ? before + '\n\n' + data.body : data.body;
        if (text.length > 10000) throw new Error('O texto combinado excede 10.000 caracteres. Ajuste o rascunho.');
        body.value = text;
        body.dispatchEvent(new Event('input', {bubbles: true}));
        body.focus();
        status.textContent = 'Modelo inserido, ainda não enviado. Revise antes de responder.';
      } catch (error) { status.textContent = error.message || 'Não foi possível carregar o modelo.'; }
      finally { button.disabled = false; }
    });
  });
})();
