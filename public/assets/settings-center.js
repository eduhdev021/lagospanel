(() => {
 const center=document.querySelector('[data-settings-center]'); if(!center)return;
 const input=center.querySelector('#settings-search'),items=[...center.querySelectorAll('[data-settings-item]')],buttons=[...center.querySelectorAll('[data-settings-filter]')];let category='all';
 const normalize=value=>value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9 ]/g,'');
 function update(){let count=0;const query=normalize(input.value.trim());items.forEach(item=>{const visible=(category==='all'||category===item.dataset.category)&&normalize(item.textContent).includes(query);item.hidden=!visible;if(visible)count++;});buttons.forEach(button=>{const active=button.dataset.settingsFilter===category;button.classList.toggle('is-active',active);button.setAttribute('aria-pressed',String(active));});center.querySelector('[data-settings-empty]').hidden=count>0;center.querySelector('[data-settings-result]').textContent=`${count} ${count===1?'configuração':'configurações'}`;center.querySelector('[data-settings-count]').textContent=items.length;}
 input.addEventListener('input',update);buttons.forEach(button=>button.addEventListener('click',()=>{category=button.dataset.settingsFilter;update();}));center.querySelector('[data-settings-reset]').addEventListener('click',()=>{category='all';input.value='';update();input.focus();});update();
})();
