(() => {
'use strict';
// Native validation must explain why Save did not submit, including collapsed fields.
const forms = document.querySelectorAll('.panel-content form');
const reveal = field => {
  for (let parent = field.parentElement; parent; parent = parent.parentElement) {
    if (parent.tagName === 'DETAILS') parent.open = true;
  }
};
forms.forEach(form => {
  let scheduled = false;
  let summary;
  form.addEventListener('invalid', event => {
    reveal(event.target);
    event.target.setAttribute('aria-invalid', 'true');
    if (scheduled) return;
    scheduled = true;
    setTimeout(() => {
      scheduled = false;
      if (!summary) {
        summary = document.createElement('div');
        summary.className = 'alert alert-danger form-validation-summary';
        summary.setAttribute('role', 'alert');
        form.prepend(summary);
      }
      summary.replaceChildren();
      const title = document.createElement('strong');
      title.textContent = 'Ainda não foi salvo. Confira os campos abaixo:';
      summary.append(title);
      const list = document.createElement('ul');
      form.querySelectorAll(':invalid').forEach(field => {
        if (!field.willValidate) return;
        const item = document.createElement('li');
        const link = document.createElement('button');
        link.type = 'button';
        const label = field.labels?.[0]?.textContent?.trim() || field.closest('.field')?.querySelector('label')?.textContent?.trim() || field.name;
        link.textContent = `${label}: ${field.validationMessage}`;
        link.addEventListener('click', () => { reveal(field); field.focus(); field.reportValidity(); });
        item.append(link); list.append(item);
      });
      summary.append(list);
      summary.scrollIntoView({block:'center'});
    }, 0);
  }, true);
  form.addEventListener('input', event => {
    if (event.target.validity?.valid) event.target.removeAttribute('aria-invalid');
  });
});
})();
