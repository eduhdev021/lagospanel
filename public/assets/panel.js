/**
 * LagosPanel — scripts
 */
(function () {
  'use strict';

  // Toggle do sidebar (mobile)
  var toggle = document.getElementById('lagosMenuToggle');
  var sidebar = document.getElementById('lagosSidebar');
  var overlay = document.getElementById('lagosOverlay');

  function closeSidebar() {
    document.body.classList.remove('sidebar-open');
  }

  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      document.body.classList.toggle('sidebar-open');
    });
    if (overlay) overlay.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeSidebar();
    });
  }

  // Tema claro/escuro 🌙
  document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dark = document.body.classList.toggle('theme-dark');
      document.cookie = 'lagos_theme=' + (dark ? 'dark' : 'light') + ';path=/;max-age=31536000';
    });
  });

  // Copiar (chave de API / código Pix)
  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var text = btn.getAttribute('data-copy');
      function done() {
        var old = btn.textContent;
        btn.textContent = btn.getAttribute('data-copied') || 'Copiado!';
        setTimeout(function () { btn.textContent = old; }, 2200);
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(function () { fallback(); });
      } else { fallback(); }
      function fallback() {
        var ta = document.createElement('textarea');
        ta.value = text; document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
        document.body.removeChild(ta);
      }
    });
  });

  // Fechar modal
  document.querySelectorAll('[data-close-modal]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (e.target === el) el.classList.remove('is-open');
    });
  });
  document.querySelectorAll('[data-close-btn]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var m = btn.closest('.modal-backdrop');
      if (m) m.classList.remove('is-open');
    });
  });
})();
