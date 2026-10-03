(function(){'use strict';var box=document.querySelector('[data-ai-chat]');if(!box)return;var tries=0;
 async function poll(){var pending=box.querySelector('[data-state="queued"],[data-state="processing"]');if(!pending)return;
 if(++tries>40){box.querySelector('[data-ai-status]').textContent='A resposta está demorando. Atualize a conversa em instantes ou abra um chamado humano.';return;}
 try{var r=await fetch(box.dataset.stateUrl,{credentials:'same-origin',headers:{'Accept':'application/json'}});if(!r.ok)throw new Error('A sessão expirou ou o limite de consultas foi atingido. Atualize a página.');var data=await r.json();
 data.turns.forEach(function(turn){var el=box.querySelector('[data-ai-turn="'+Number(turn.id)+'"]');if(!el)return;el.dataset.state=turn.status;
 var sources=el.querySelector('[data-ai-sources]');if(sources&&turn.status==='done'&&turn.web_status){sources.replaceChildren();var note=document.createElement('p');note.textContent=turn.web_status==='searched'?'Fontes encontradas pela pesquisa (trechos; confira os links):':turn.web_status==='empty'?'Pesquisa sem resultados públicos válidos.':'Pesquisa indisponível; informações atuais não foram confirmadas.';sources.appendChild(note);(turn.sources||[]).forEach(function(src){var url;try{url=new URL(src.url);}catch(e){return;}if(!['https:','http:'].includes(url.protocol))return;var line=document.createElement('p'),a=document.createElement('a');a.href=url.href;a.textContent='['+src.id+'] '+src.title;a.target='_blank';a.rel='noopener noreferrer nofollow';line.appendChild(a);sources.appendChild(line);});}
var text=el.querySelector('[data-ai-answer]');if(turn.status==='done')text.textContent=turn.answer;else if(turn.status==='failed')text.textContent='Não foi possível concluir esta resposta. Não houve repetição automática. Abra um chamado ou envie uma nova mensagem.';});
 setTimeout(poll,3000);
 }catch(e){box.querySelector('[data-ai-status]').textContent='Não foi possível atualizar a resposta. Use Atualizar conversa.';}}
 setTimeout(poll,1500);
})();
