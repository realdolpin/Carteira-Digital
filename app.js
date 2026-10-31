const CATS={cripto:'Criptomoedas',tesouro:'Tesouro',imoveis:'Imóveis',bolsa:'Bolsa'};
const COL={cripto:'#e9b44c',tesouro:'#5fb7a5',imoveis:'#e4572e',bolsa:'#7d9bd6'};
const GLOSS=[['Acção','Parte do capital de uma empresa. Ao comprar, tornas-te sócio.'],['Dividendo','Parte do lucro que a empresa distribui aos accionistas.'],['Obrigação do Tesouro','Empréstimo ao Estado a médio/longo prazo, com juros.'],['Bilhete do Tesouro','Título do Estado de curto prazo, de baixo risco.'],['BODIVA','Bolsa de Dívida e Valores de Angola, onde se negoceiam títulos.'],['Criptomoeda','Moeda digital descentralizada, como Bitcoin e Ethereum.'],['Fundo imobiliário','Investes em imóveis sem comprar um inteiro e recebes rendas.'],['Volatilidade','Quanto o preço sobe e desce. Cripto costuma ser a mais alta.'],['Liquidez','Rapidez com que transformas um activo em dinheiro.'],['Diversificação','Repartir o dinheiro por vários activos para reduzir o risco.'],['Corretora','Intermediária autorizada a comprar e vender activos por ti.'],['Capitalização','Valor total de mercado de um activo ou empresa.']];
const $=s=>document.querySelector(s), fmt=n=>Math.round(n).toLocaleString('pt-PT')+' Kz';
const pct=n=>(n>=0?'+':'')+n.toFixed(2).replace('.',',')+'%';
let S=null,filter='todos',prev={},cur=null;

async function api(body){
  const r=await fetch('api.php',{method:'POST',body:new URLSearchParams(body||{a:'state'})});
  if(r.status===401){location='index.php';return}
  const j=await r.json(); if(!r.ok) throw new Error(j.error); return j;
}
const px=s=>S.assets.find(a=>a.sym===s);
function render(){
  const H=S.holdings.map(h=>{const a=px(h.sym);return{...h,a,val:h.units*a.price}});
  const val=H.reduce((t,h)=>t+h.val,0), inv=H.reduce((t,h)=>t+h.invested,0), bal=+S.user.balance, tot=bal+val;
  $('#uname').textContent=S.user.name; $('#total').textContent=fmt(tot);
  $('#bal').textContent=fmt(bal); $('#val').textContent=fmt(val);
  const g=val-inv, gc=$('#gain'); gc.textContent=(g>=0?'+':'')+fmt(g); gc.className=g>=0?'up':'dn';
  $('#pl').textContent=inv? 'Rendimento total: '+pct(g/inv*100):'Ainda sem investimentos. Escolhe um activo no mercado.';
  // alocação
  const by={}; H.forEach(h=>by[h.a.cat]=(by[h.a.cat]||0)+h.val);
  let acc=0,stops=[],lg='';
  Object.keys(CATS).forEach(c=>{const p=tot?(by[c]||0)/tot*100:0; if(p>0){stops.push(`${COL[c]} ${acc}% ${acc+p}%`);acc+=p}
    lg+=`<div><i style="background:${COL[c]}"></i>${CATS[c]}<b>${p.toFixed(1).replace('.',',')}%</b></div>`});
  lg+=`<div><i style="background:#2b3d57"></i>Saldo livre<b>${(tot?bal/tot*100:100).toFixed(1).replace('.',',')}%</b></div>`;
  $('#donut').style.background=`conic-gradient(${stops.concat(`#2b3d57 ${acc}% 100%`).join(',')})`;
  $('#invPct').textContent=Math.round(acc)+'%'; $('#legend').innerHTML=lg;
  // mercado
  $('#chips').innerHTML=['todos',...Object.keys(CATS)].map(c=>`<button class="${c===filter?'on':''}" data-f="${c}">${CATS[c]||'Todos'}</button>`).join('');
  $('#list').innerHTML=S.assets.filter(a=>filter==='todos'||a.cat===filter).map(a=>{
    const ch=(a.price/a.open_price-1)*100, fl=prev[a.sym]&&prev[a.sym]!==a.price?(a.price>prev[a.sym]?'fu':'fd'):'';
    return `<div class="asset ${fl}"><i class="dot" style="background:${COL[a.cat]}"></i><div class="nm"><b>${a.name}</b><small>${a.sym} · ${CATS[a.cat]}</small></div>
    <div class="pr"><b>${fmt(a.price)}</b><small class="${ch>=0?'up':'dn'}">${pct(ch)}</small></div><button class="btn sm" data-i="${a.sym}">Investir</button></div>`}).join('');
  // carteira
  $('#plist').innerHTML=H.length?H.map(h=>{const p=(h.val/h.invested-1)*100;
    return `<div class="asset"><i class="dot" style="background:${COL[h.a.cat]}"></i><div class="nm"><b>${h.a.name}</b><small>${h.units.toFixed(h.units<1?6:2)} un. · ${(h.val/tot*100).toFixed(1).replace('.',',')}% do património</small></div>
    <div class="pr"><b>${fmt(h.val)}</b><small class="${p>=0?'up':'dn'}">${pct(p)}</small></div>
    <div class="acts"><button class="btn sm" data-i="${h.sym}">+</button><button class="btn sm ghost" data-s="${h.sym}">Vender</button></div></div>`}).join('')
    :'<p class="empty">A tua carteira está vazia. Investe a partir de 10.000 Kz no separador Mercado.</p>';
  S.assets.forEach(a=>prev[a.sym]=a.price);
}
async function tick(){try{S=await api();render()}catch(e){}}
function open(sym,mode){
  const a=px(sym),h=S.holdings.find(x=>x.sym===sym),max=mode==='i'?+S.user.balance:(h?h.units*a.price:0);
  cur={sym,mode,max}; $('#mt').textContent=(mode==='i'?'Investir em ':'Vender ')+a.name;
  $('#ms').textContent=(mode==='i'?'Saldo disponível: ':'Disponível para vender: ')+fmt(max);
  $('#mh').textContent=mode==='i'?'Mínimo: 10.000 Kz':''; $('#amt').value=''; $('#amt').min=mode==='i'?10000:1; $('#amt').step='any';
  $('#mb').textContent=mode==='i'?'Confirmar investimento':'Confirmar venda';
  $('#quick').innerHTML=[25,50,100].map(p=>`<button type="button" data-p="${p}">${p}%</button>`).join('');
  $('#modal').hidden=false; $('#amt').focus();
}
function toast(m,bad){const t=$('#toast');t.textContent=m;t.className='toast show'+(bad?' bad':'');setTimeout(()=>t.className='toast',2800)}
document.addEventListener('click',e=>{
  const t=e.target; if(t.dataset.f){filter=t.dataset.f;render()}
  if(t.dataset.i)open(t.dataset.i,'i'); if(t.dataset.s)open(t.dataset.s,'s');
  if(t.dataset.p)$('#amt').value=Math.floor(cur.max*t.dataset.p/100);
  if(t.dataset.t){document.querySelectorAll('#tabs button').forEach(b=>b.classList.toggle('on',b===t));
    ['market','port','gloss'].forEach(s=>$('#'+s).hidden=s!==t.dataset.t)}
  if(t.id==='mc'||t.id==='modal')$('#modal').hidden=true;
});
$('#mf').onsubmit=async e=>{e.preventDefault();
  try{S=await api({a:cur.mode==='i'?'invest':'sell',sym:cur.sym,amount:$('#amt').value});
    $('#modal').hidden=true;render();toast(cur.mode==='i'?'Investimento realizado':'Venda realizada')}
  catch(err){toast(err.message,true)}};
$('#gl').innerHTML=GLOSS.map(g=>`<dt>${g[0]}</dt><dd>${g[1]}</dd>`).join('');
tick(); setInterval(tick,3000);
