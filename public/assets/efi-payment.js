(function () {
  'use strict';
  var qrHost = document.querySelector('[data-pix-qr]');
  var code = document.querySelector('[data-pix-code]');
  if (!qrHost || !code || typeof window.qrcode !== 'function') return;
  try {
    var qr = window.qrcode(0, 'M');
    qr.addData(code.value || code.textContent || '');
    qr.make();
    qrHost.innerHTML = qr.createSvgTag({ scalable: true, margin: 0 });
    qrHost.setAttribute('aria-label', 'QR Code Pix da fatura');
  } catch (error) {
    qrHost.hidden = true;
  }
})();
