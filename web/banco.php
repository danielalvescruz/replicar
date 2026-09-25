<?php
require __DIR__ . '/lib.php';
exigirLogin();
$sites = sitesBanco();
$referencia = '';
foreach ($sites as $s) {
    if (!empty($s['banco']['referencia'])) {
        $referencia = $s['nome'];
    }
}
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Replicar banco</title>
<link rel="stylesheet" href="estilo.css">
<style>
.destinos small.tag { display: inline-block; margin-left: 6px; padding: 0 6px; border: 1px solid var(--border); border-radius: 99px; font-size: 11px; }
.site-banco { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 3px 10px; align-items: center; border-top: 1px solid var(--border); padding-top: 8px; }
.site-banco .nome { font-size: 14px; font-weight: 600; }
.site-banco .barra { grid-column: 1 / 3; }
.site-banco .canal-msg { grid-column: 1 / 3; }
</style>
</head>
<body>
<main>
    <?php $pagina = 'banco'; include __DIR__ . '/menu.php'; ?>

    <div class="card">
        <label class="titulo" for="sql">SQL</label>
        <textarea id="sql" rows="10" placeholder="Cole o SQL aqui. Pode ter vários comandos separados por ;"></textarea>
        <div class="dica">
            O prefixo da tabela (ex: <code>nsite_</code>) é trocado pelo prefixo de cada site.
            <?php if ($referencia): ?>Tabelas que não existem num site são criadas copiando a estrutura do <strong><?= htmlspecialchars($referencia) ?></strong> (referência).<?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="acoes-destinos">
            <label class="titulo" style="margin:0">Sites</label>
            <span><button id="todos">Todos</button> <button id="nenhum">Nenhum</button></span>
        </div>
        <?php if (!$sites): ?>
        <div class="dica">Nenhum site com banco de dados. Cadastre na tela <a href="sites.php">Sites</a>.</div>
        <?php endif; ?>
        <div class="destinos" id="destinos">
            <?php foreach ($sites as $s): $b = $s['banco']; ?>
            <label>
                <input type="checkbox" value="<?= htmlspecialchars($s['id']) ?>" data-usar="<?= htmlspecialchars($b['usar']) ?>"<?= $b['usar'] === 'nao' ? ' disabled' : '' ?>>
                <span><?= htmlspecialchars($s['nome']) ?><?php if (!empty($b['referencia'])): ?><small class="tag">referência</small><?php endif; ?><?php if ($b['usar'] === 'perguntar'): ?><small class="tag">perguntar</small><?php endif; ?><?php if ($b['usar'] === 'nao'): ?><small class="tag">desligado</small><?php endif; ?>
                    <small><?= htmlspecialchars($b['banco']) ?> · <?= htmlspecialchars($b['prefixo']) ?></small></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card botoes">
        <button id="executar" class="principal">Executar</button>
        <button id="cancelar" disabled>Cancelar</button>
    </div>

    <div class="card" id="progresso" hidden>
        <div class="geral-topo"><label class="titulo" style="margin:0">Progresso geral</label><span id="geral-texto"></span></div>
        <div class="barra grande"><div id="geral-barra"></div></div>
        <div class="sites" id="sites"></div>
    </div>

    <pre id="log">Cole o SQL, marque os sites e clique em Executar.</pre>
</main>

<script>
const $ = id => document.getElementById(id);
const guardar = (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} };
const ler = (k, padrao) => { try { const v = localStorage.getItem(k); return v ? JSON.parse(v) : padrao; } catch (e) { return padrao; } };
const caixas = () => [...document.querySelectorAll('#destinos input:not(:disabled)')];
const NOMES = <?= json_encode(array_column($sites, 'nome', 'id'), JSON_UNESCAPED_UNICODE) ?>;
const ROTULOS = { aguardando: 'Aguardando', enviando: 'Executando', ok: 'OK', erro: 'Erro', parcial: 'Parcial', pulado: 'Pulado', cancelado: 'Cancelado' };
const FINAIS = ['ok', 'erro', 'parcial', 'pulado', 'cancelado'];
let controle = null;
let linhas = {};

// Marcados da última vez; na primeira vez, os que estão como "usar: sim"
const salvos = ler('banco_destinos', null);
caixas().forEach(c => {
    c.checked = salvos ? salvos.includes(c.value) : c.dataset.usar === 'sim';
    c.addEventListener('change', salvarDestinos);
});
function salvarDestinos() { guardar('banco_destinos', caixas().filter(c => c.checked).map(c => c.value)); }
$('todos').onclick = () => { caixas().forEach(c => c.checked = true); salvarDestinos(); };
$('nenhum').onclick = () => { caixas().forEach(c => c.checked = false); salvarDestinos(); };
try { $('sql').value = sessionStorage.getItem('banco_sql') || ''; } catch (e) {}
$('sql').addEventListener('input', () => { try { sessionStorage.setItem('banco_sql', $('sql').value); } catch (e) {} });

function esc(s) { return String(s).replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c])); }
function log(texto, classe) {
    const el = $('log');
    const perto = el.scrollHeight - el.scrollTop - el.clientHeight < 40;
    el.insertAdjacentHTML('beforeend', (classe ? '<span class="' + classe + '">' + esc(texto) + '</span>' : esc(texto)) + '\n');
    if (perto) el.scrollTop = el.scrollHeight;
}

function iniciarProgresso(ids) {
    linhas = {};
    $('sites').innerHTML = '';
    ids.forEach(id => {
        const el = document.createElement('div');
        el.className = 'site-banco';
        el.innerHTML = '<span class="nome"></span><span class="canal-status"></span><div class="barra"><div></div></div><div class="canal-msg"></div>';
        el.querySelector('.nome').textContent = NOMES[id] || id;
        $('sites').appendChild(el);
        linhas[id] = { el, estado: 'aguardando' };
        pintar(id, { estado: 'aguardando', feitos: 0, total: 0, pct: null, msg: '' });
    });
    $('progresso').hidden = false;
    atualizarGeral();
}
function pintar(id, ev) {
    const l = linhas[id];
    if (!l) return;
    l.estado = ev.estado;
    const medido = ev.pct !== null && ev.pct !== undefined;
    let texto = ROTULOS[ev.estado];
    if (ev.total && ev.estado !== 'pulado') texto += ' ' + Math.min(ev.feitos, ev.total) + '/' + ev.total + ' comandos';
    const status = l.el.querySelector('.canal-status');
    status.textContent = texto;
    status.className = 'canal-status c-' + ev.estado;
    const barra = l.el.querySelector('.barra');
    let pct = 0;
    if (ev.estado === 'enviando') pct = medido ? ev.pct : 0;
    else if (ev.estado !== 'aguardando') pct = 100;
    barra.className = 'barra c-' + ev.estado + (ev.estado === 'enviando' && !medido ? ' indeterminada' : '');
    barra.firstChild.style.width = pct + '%';
    const msg = l.el.querySelector('.canal-msg');
    msg.className = 'canal-msg c-' + ev.estado;
    msg.textContent = ev.msg || '';
    msg.title = ev.msg || '';
}
function atualizarGeral() {
    const todas = Object.values(linhas);
    const feitas = todas.filter(l => FINAIS.includes(l.estado)).length;
    const erros = todas.filter(l => ['erro', 'parcial'].includes(l.estado)).length;
    $('geral-barra').style.width = (todas.length ? feitas / todas.length * 100 : 0) + '%';
    $('geral-barra').style.background = erros ? 'var(--warn)' : (feitas === todas.length && todas.length ? 'var(--ok)' : '');
    $('geral-texto').textContent = feitas + ' de ' + todas.length + ' sites' + (erros ? ' · ' + erros + ' com erro' : '');
}
function encerrar(cancelado) {
    Object.keys(linhas).forEach(id => {
        if (!FINAIS.includes(linhas[id].estado)) pintar(id, { estado: cancelado ? 'cancelado' : 'pulado', feitos: 0, total: 0, pct: null, msg: '' });
    });
    atualizarGeral();
}

function ocupado(sim) {
    $('executar').disabled = sim;
    $('cancelar').disabled = !sim;
}

async function executar() {
    const sql = $('sql').value.trim();
    const destinos = caixas().filter(c => c.checked).map(c => c.value);
    if (!sql) { $('sql').focus(); return; }
    if (!destinos.length) { alert('Marque pelo menos um site.'); return; }
    if (!confirm('Executar o SQL em ' + destinos.length + ' site(s)?\n\n' + destinos.map(id => NOMES[id]).join('\n'))) return;

    $('log').textContent = '';
    iniciarProgresso(destinos);
    ocupado(true);
    controle = new AbortController();
    try {
        const resp = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Replicar': '1' },
            body: JSON.stringify({ acao: 'banco', sql, destinos }),
            signal: controle.signal,
        });
        if (resp.status === 401) { location.href = 'login.php'; return; }
        const leitor = resp.body.getReader();
        const dec = new TextDecoder();
        let resto = '';
        while (true) {
            const { value, done } = await leitor.read();
            if (done) break;
            resto += dec.decode(value, { stream: true });
            let i;
            while ((i = resto.indexOf('\n')) >= 0) {
                const linha = resto.slice(0, i);
                resto = resto.slice(i + 1);
                if (linha) tratar(JSON.parse(linha));
            }
        }
    } catch (e) {
        log(e.name === 'AbortError' ? 'Cancelado.' : 'Erro: ' + e.message, 'erro');
        encerrar(true);
    }
    ocupado(false);
    controle = null;
}

function tratar(ev) {
    switch (ev.t) {
        case 'out': log(ev.s, /ERRO/.test(ev.s) ? 'erro' : ''); break;
        case 'resp': log('  → ' + ev.s, 'resp'); break;
        case 'erro': log(ev.s, 'erro'); break;
        case 'etapa':
            // Sites não marcados aparecem como "pulado" na saída - não entram nas barras
            if (linhas[ev.id]) { pintar(ev.id, ev); atualizarGeral(); }
            break;
        case 'fim':
            encerrar(false);
            log('\nTerminado.', ev.codigo === 0 ? 'ok' : 'erro');
            break;
    }
}

$('executar').onclick = executar;
$('cancelar').onclick = () => controle && controle.abort();
</script>
</body>
</html>
