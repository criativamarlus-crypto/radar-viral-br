<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/feeds.php';
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Radar Viral BR</title>
<style>
:root{font-family:system-ui,-apple-system,Segoe UI,sans-serif;color-scheme:dark}*{box-sizing:border-box}body{margin:0;background:#0b1020;color:#e8ecf8}header{background:linear-gradient(120deg,#162448,#0a1227);padding:40px max(20px,calc((100vw - 1120px)/2))}h1{font-size:clamp(30px,4vw,48px);margin:0 0 8px}h1 span{color:#ffc349}header p{color:#b9c8e5;max-width:650px}.wrap{max-width:1120px;margin:auto;padding:24px 18px}.notice{padding:16px;border-left:4px solid #ffc349;background:#182846;border-radius:8px;color:#c7d5ef;margin-bottom:24px}.tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px}.tabs button,.card button{background:#263a64;color:#fff;border:1px solid #394c74;border-radius:10px;padding:12px 15px;cursor:pointer}.tabs button.on{background:#ffc349;color:#142036;font-weight:700}.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:16px}.card{border:1px solid #2c3b60;background:#171f34;border-radius:14px;padding:20px;display:flex;flex-direction:column;gap:12px}.card h2{font-size:19px;line-height:1.3;margin:0}.card small{color:#a9b8d4}.card a{color:#8ed4ff;overflow-wrap:anywhere}.card button{margin-top:auto}.label{font-size:12px;color:#ffcc58;font-weight:800;text-transform:uppercase}footer{text-align:center;color:#95a6c4;padding:40px}.loading{color:#b4c3da}
</style></head><body><header><h1>📡 Radar <span>Viral BR</span></h1><p>Manchetes do Brasil e do mundo organizadas para seu personagem reagir em vídeos curtos.</p></header><main class="wrap"><div class="notice">Notícias obtidas de RSS; isto não é uma classificação oficial de tendências. Abra o artigo e confira os fatos antes de gerar o vídeo. Prints e imagens reais serão adicionados em uma próxima etapa.</div><div class="tabs" id="tabs"><?php foreach(array_keys(radar_categories()) as $cat):?><button data-cat="<?=htmlspecialchars($cat,ENT_QUOTES,'UTF-8')?>"><?=htmlspecialchars($cat,ENT_QUOTES,'UTF-8')?></button><?php endforeach;?></div><p id="status" class="loading">Carregando notícias...</p><section id="cards" class="cards"></section></main><footer>Radar Viral BR • Curadoria de pautas e reacts</footer>
<script>
let current='Geral',items=[];
const cards=document.getElementById('cards'),status=document.getElementById('status');
const buttons=[...document.querySelectorAll('[data-cat]')];
function react(n){
const p='Crie um roteiro de react de 20 a 35 segundos, em português brasileiro, para um personagem fictício adulto, negro, robusto, de óculos, corrente de ouro e sorriso marcante, com tom natural, expressivo e divertido. Manchete original: '+n.title+'. Fonte: '+n.source+'. URL: '+n.url+'. Comece com um gancho chamativo sem distorcer os fatos, explique apenas o que puder ser confirmado, distinga opinião de fato e termine com uma pergunta. Sugira composição vertical 9:16, com print REAL da matéria acima e personagem abaixo. Se faltar informação, solicite a matéria em vez de inventar detalhes.';
if(navigator.clipboard?.writeText){navigator.clipboard.writeText(p).then(()=>alert('Prompt copiado!')).catch(()=>window.prompt('Copie o prompt:',p))}else window.prompt('Copie o prompt:',p);
}
async function load(cat){
current=cat;buttons.forEach(b=>b.classList.toggle('on',b.dataset.cat===cat));
status.textContent='Carregando '+cat+'...';cards.replaceChildren();
try{const res=await fetch('api.php?categoria='+encodeURIComponent(cat));const data=await res.json();if(!res.ok)throw Error(data.error||'Falha na coleta');
items=data.items||[];status.textContent=items.length+' manchetes encontradas • '+cat;
for(const n of items){const el=document.createElement('article');el.className='card';
const tag=document.createElement('div');tag.className='label';tag.textContent=n.category;
const h=document.createElement('h2');h.textContent=n.title;
const small=document.createElement('small');small.textContent=n.source+' · '+new Date(n.published).toLocaleString('pt-BR');
const a=document.createElement('a');a.textContent='Abrir notícia original';a.href=n.url;a.target='_blank';a.rel='noopener noreferrer';
const button=document.createElement('button');button.textContent='Copiar prompt de react';button.addEventListener('click',()=>react(n));
el.append(tag,h,small,a,button);cards.append(el);
}
}catch(e){status.textContent='Não foi possível buscar notícias: '+e.message;}}
buttons.forEach(b=>b.addEventListener('click',()=>load(b.dataset.cat)));load(current);
</script></body></html>
