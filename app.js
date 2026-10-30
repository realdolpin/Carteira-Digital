const $ = s => document.querySelector(s);
const kz = n => Number(n).toLocaleString('pt-AO', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' Kz';
const pct = n => (n >= 0 ? '+' : '') + n.toFixed(3) + '%';
const cls = n => n >= 0 ? 'up' : 'down';
let S = null, filtro = 'Todos', ativoSel = null;

function spark(pts) {
  if (!pts || pts.length < 2) return '<svg class="spark"></svg>';
  const mn = Math.min(...pts), mx = Math.max(...pts), r = (mx - mn) || 1;
  const d = pts.map((p, i) => `${(i / (pts.length - 1) * 100).toFixed(1)},${(30 - (p - mn) / r * 28).toFixed(1)}`).join(' ');
  const c = pts[pts.length - 1] >= pts[0] ? '#22c55e' : '#ef4444';
  return `<svg class="spark" viewBox="0 0 100 32" preserveAspectRatio="none"><polyline points="${d}" fill="none" stroke="${c}" stroke-width="1.6" vector-effect="non-scaling-stroke"/></svg>`;
}

async function api(acao, body) {
  const r = await fetch('api.php?a=' + acao, body ? {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)} : {});
  const txt = await r.text();
  let j; try { j = JSON.parse(txt); } catch (e) { throw new Error('Resposta inválida do servidor (verifique o PHP/MySQL): ' + txt.replace(/<[^>]+>/g, ' ').trim().slice(0, 300)); }
  if (r.status === 401) { mostrarAuth(); throw new Error('auth'); }
  if (!r.ok) throw new Error(j.erro || 'Erro');
  return j;
}

function render() {
  $('#auth').hidden = true; $('#app').hidden = false;
  $('#user').textContent = S.nome; $('#grafico').innerHTML = grafico(S.patrimonio_hist);
  $('#saldo').textContent = kz(S.saldo);
  $('#investido').textContent = kz(S.investido);
  $('#atual').textContent = kz(S.valor_atual);
  $('#lucro').innerHTML = `<span class="${cls(S.lucro)}">${kz(S.lucro)}</span>`;
  $('#patrimonio').textContent = kz(S.patrimonio);

  const cats = ['Todos', ...new Set(S.ativos.map(a => a.categoria))];
  $('#tabs').innerHTML = cats.map(c => `<button class="tab ${c === filtro ? 'on' : ''}" data-c="${c}">${c}</button>`).join('');
  $('#mercado').innerHTML = S.ativos.filter(a => filtro === 'Todos' || a.categoria === filtro).map(a => `
    <div class="asset">
      <span class="cat">${a.categoria}</span>
      <span class="nm">${a.nome} <small>(${a.simbolo})</small></span>
      <span class="pr">${kz(a.preco)}</span>
      <span class="${cls(a.variacao)}">${pct(a.variacao)} desde o início</span>
      ${spark(a.historico)}
      <button data-i="${a.id}">Investir</button>
    </div>`).join('');

  const porAtivo = Object.fromEntries(S.ativos.map(a => [a.id, a]));
  $('#meus').innerHTML = S.investimentos.length ? S.investimentos.map(i => `
    <tr style="${i.estado === 'resgatado' ? 'opacity:.5' : ''}">
      <td><b>${i.nome}</b><small>${i.categoria} · ${Number(i.unidades).toFixed(6)} un. · ${new Date(i.criado_em * 1000).toLocaleString('pt-AO')}</small></td>
      <td>${kz(i.valor)}</td>
      <td>${kz(i.valor_atual)}</td>
      <td class="${cls(i.lucro)}">${kz(i.lucro)}<small>${pct(i.lucro_pct)}</small></td>
      <td style="width:130px">${i.estado === 'ativo' ? spark(porAtivo[i.ativo_id]?.historico) : '<small>Resgatado</small>'}</td>
      <td>${i.estado === 'ativo' ? `<button class="sell" data-r="${i.id}">Resgatar</button>` : ''}</td>
    </tr>`).join('') : '<tr><td colspan="6" class="muted">Ainda não tem investimentos. Escolha um activo acima.</td></tr>';
  if ($('#modal').open) calcModal();
}

async function carregar() {
  try { S = await api('estado'); render(); } catch (e) { if (e.message !== 'auth') { $('#timer').textContent = '⚠ ' + e.message; if (!S) { mostrarAuth(); $('#a-erro').textContent = '⚠ ' + e.message; } } }
}

function atualizarTimer() {
  if (!S) return;
  const rest = Math.max(0, S.proxima - (S.agora + (Date.now() - S.recebido) / 1000));
  $('#timer').textContent = `Preços actualizados a cada minuto · próxima actualização em ${Math.ceil(rest)}s`;
}

function calcModal() {
  const v = parseFloat($('#m-valor').value) || 0;
  $('#m-preco').textContent = kz(ativoSel.preco);
  $('#m-saldo').textContent = kz(S.saldo);
  $('#m-un').textContent = (v / ativoSel.preco).toFixed(8);
}

document.addEventListener('click', async e => {
  const t = e.target;
  if (t.dataset.c) { filtro = t.dataset.c; render(); }
  if (t.dataset.i) {
    ativoSel = S.ativos.find(a => a.id == t.dataset.i);
    $('#m-titulo').textContent = `Investir em ${ativoSel.nome}`;
    $('#m-valor').value = 5000; $('#m-erro').textContent = '';
    calcModal(); $('#modal').showModal();
  }
  if (t.dataset.r && confirm('Resgatar este investimento ao preço actual?')) {
    try { S = await api('resgatar', {id: +t.dataset.r}); S.recebido = Date.now(); render(); } catch (er) { alert(er.message); }
  }
});
$('#m-valor').addEventListener('input', calcModal);
$('#m-cancel').onclick = () => $('#modal').close();
$('#form').addEventListener('submit', async e => {
  e.preventDefault();
  try {
    S = await api('investir', {ativo_id: ativoSel.id, valor: parseFloat($('#m-valor').value)});
    S.recebido = Date.now(); $('#modal').close(); render();
  } catch (er) { $('#m-erro').textContent = er.message; }
});
$('#reset').onclick = async () => {
  if (confirm('Repor saldo de 300.000 Kz e apagar todos os investimentos?')) { S = await api('reiniciar', {}); S.recebido = Date.now(); render(); }
};

(async function () {
  await carregar(); if (S) S.recebido = Date.now();
  setInterval(async () => { await carregar(); if (S) S.recebido = Date.now(); }, 10000);
  setInterval(atualizarTimer, 1000);
})();

// ---------- Gráfico do património ----------
function grafico(p) {
  if (!p || p.length < 2) return '<span class="muted">O gráfico aparece após o primeiro minuto de simulação…</span>';
  const v = p.map(x => x.v), mn = Math.min(...v), mx = Math.max(...v), r = (mx - mn) || 1;
  const pts = v.map((y, i) => `${(i / (v.length - 1) * 600).toFixed(1)},${(150 - (y - mn) / r * 140).toFixed(1)}`);
  const c = v[v.length - 1] >= v[0] ? '#22c55e' : '#ef4444';
  const hora = t => new Date(t * 1000).toLocaleTimeString('pt-AO', {hour: '2-digit', minute: '2-digit'});
  return `<svg viewBox="0 0 600 155" preserveAspectRatio="none" style="width:100%;height:170px">
    <polygon points="0,155 ${pts.join(' ')} 600,155" fill="${c}" opacity=".13"/>
    <polyline points="${pts.join(' ')}" fill="none" stroke="${c}" stroke-width="2" vector-effect="non-scaling-stroke"/></svg>
    <div class="legend"><span>${hora(p[0].t)} · ${kz(v[0])}</span><span>Mín ${kz(mn)} · Máx ${kz(mx)}</span><span>${hora(p[p.length - 1].t)} · ${kz(v[v.length - 1])}</span></div>`;
}

// ---------- Autenticação ----------
let modoReg = false;
function mostrarAuth() { $('#auth').hidden = false; $('#app').hidden = true; }
$('#auth-toggle').onclick = () => {
  modoReg = !modoReg;
  $('#a-nome').hidden = !modoReg; $('#a-nome').required = modoReg;
  $('#a-ok').textContent = modoReg ? 'Criar conta' : 'Entrar';
  $('#auth-toggle').textContent = modoReg ? 'Já tenho conta' : 'Criar conta (recebe 300.000 Kz)';
  $('#auth-sub').textContent = modoReg ? 'Crie a sua conta e comece com 300.000 Kz.' : 'Entre para ver os seus investimentos.';
  $('#a-erro').textContent = '';
};
$('#auth-form').addEventListener('submit', async e => {
  e.preventDefault();
  try {
    S = await api(modoReg ? 'registar' : 'entrar', {nome: $('#a-nome').value, email: $('#a-email').value, senha: $('#a-senha').value});
    S.recebido = Date.now(); $('#a-senha').value = ''; render();
  } catch (er) { if (er.message !== 'auth') $('#a-erro').textContent = er.message; }
});
$('#sair').onclick = async () => { await fetch('api.php?a=sair', {method: 'POST'}); S = null; mostrarAuth(); };
