<?php
require __DIR__ . '/lib.php';
exigirLogin();
$pasta_inicial = valor($_GET, 'pasta', '');
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Replicar</title>
<link rel="stylesheet" href="estilo.css">
</head>
<body>
<main>
    <?php $pagina = 'replicar'; include __DIR__ . '/menu.php'; ?>

    <div class="card">
        <label class="titulo" for="caminho">Pasta ou arquivo</label>
        <div class="linha">
            <input type="text" id="caminho" placeholder="<?= MODO_SERVIDOR ? 'Cole o caminho da pasta no servidor (Ctrl+L no Dolphin) - ex: sftp://daniel@100.96.10.71/home/ricardo/web/...' : 'Cole o caminho (Ctrl+L no Dolphin) - local, sftp:// ou kio-fuse' ?>" value="<?= htmlspecialchars($pasta_inicial) ?>">
            <button id="limpar" title="Limpar">✕</button>
        </div>
        <div class="status" id="status"></div>
        <div class="recentes" id="recentes"></div>
    </div>

    <div class="card">
        <label class="titulo" for="arquivo">Arquivo a replicar</label>
        <input type="text" id="arquivo" placeholder="Vazio = último arquivo alterado da pasta">
        <div class="dica">Para enviar uma subpasta inteira escreva <code>pasta=nome da pasta</code>.</div>
    </div>

    <div class="card">
        <div class="acoes-destinos">
            <label class="titulo" style="margin:0">Destinos</label>
            <span><button id="todos">Todos</button> <button id="nenhum">Nenhum</button></span>
        </div>
        <div class="destinos" id="destinos">
            <?php foreach (destinos() as $d): ?>
            <label>
                <input type="checkbox" value="<?= $d['id'] ?>">
                <span><?= htmlspecialchars($d['nome']) ?><?php if (!empty($d['obs'])): ?><small><?= htmlspecialchars($d['obs']) ?></small><?php endif; ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card botoes">
        <button id="analisar">Analisar</button>
        <button id="replicar" class="principal">Replicar</button>
        <button id="cancelar" disabled>Cancelar</button>
    </div>

    <div class="card" id="progresso" hidden>
        <div class="geral-topo"><label class="titulo" style="margin:0">Progresso geral</label><span id="geral-texto"></span></div>
        <div class="barra grande"><div id="geral-barra"></div></div>
        <div class="sites" id="sites"></div>
    </div>

    <pre id="log">Cole o caminho e clique em Analisar para conferir antes de enviar.</pre>
</main>

<script>
const $ = id => document.getElementById(id);
const ETAPAS = <?= json_encode(etapas(), JSON_UNESCAPED_UNICODE) ?>;
const ROTULOS = { aguardando: 'Aguardando', enviando: 'Enviando…', ok: 'Enviado', erro: 'Erro', parcial: 'Parcial',
    excecao: 'Exceção - não enviado', pulado: 'Nada enviado', cancelado: 'Cancelado' };
const FINAIS = ['ok', 'erro', 'parcial', 'excecao', 'pulado', 'cancelado'];
let linhas = {};
const guardar = (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} };
const ler = (k, padrao) => { try { const v = localStorage.getItem(k); return v ? JSON.parse(v) : padrao; } catch (e) { return padrao; } };
const caixas = () => [...document.querySelectorAll('#destinos input')];

let controle = null;

// Destinos marcados da última vez
const marcadosSalvos = ler('replicar_destinos', null);
caixas().forEach(c => {
    if (marcadosSalvos) c.checked = marcadosSalvos.includes(c.value);
    c.addEventListener('change', salvarDestinos);
});
function salvarDestinos() { guardar('replicar_destinos', caixas().filter(c => c.checked).map(c => c.value)); }
$('todos').onclick = () => { caixas().forEach(c => c.checked = true); salvarDestinos(); };
$('nenhum').onclick = () => { caixas().forEach(c => c.checked = false); salvarDestinos(); };

// Pastas recentes
function mostrarRecentes() {
    const box = $('recentes');
    box.innerHTML = '';
    ler('replicar_recentes', []).forEach(p => {
        const b = document.createElement('button');
        b.textContent = p.replace(/^.*\/(administrator\/components|\d{4})\//, '…/$1/');
        b.title = p;
        b.onclick = () => { $('caminho').value = p; $('arquivo').value = ''; interpretar(); };
        box.appendChild(b);
    });
}
function adicionarRecente(p) {
    const lista = ler('replicar_recentes', []).filter(x => x !== p);
    lista.unshift(p);
    guardar('replicar_recentes', lista.slice(0, 8));
    mostrarRecentes();
}

async function chamar(corpo, sinal) {
    const r = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Replicar': '1' },
        body: JSON.stringify(corpo),
        signal: sinal,
    });
    if (r.status === 401) { location.href = 'login.php'; throw new Error('Sessão expirada'); }
    return r;
}

// Mostra se é local ou remoto e, se o caminho for de um arquivo, preenche o campo do arquivo
async function interpretar() {
    const caminho = $('caminho').value.trim();
    const st = $('status');
    if (!caminho) { st.innerHTML = ''; return null; }
    const info = await (await chamar({ acao: 'interpretar', caminho })).json();
    if (info.erro) {
        st.innerHTML = '<span style="color:var(--err)">' + esc(info.erro) + '</span>';
        return null;
    }
    st.innerHTML = '<span class="tag">' + esc(info.onde) + '</span>' + esc(info.pasta);
    if (info.arquivo) $('arquivo').value = info.arquivo;
    return info;
}
$('caminho').addEventListener('change', interpretar);
$('caminho').addEventListener('paste', () => setTimeout(interpretar, 0));
$('limpar').onclick = () => { $('caminho').value = ''; $('arquivo').value = ''; $('status').innerHTML = ''; $('caminho').focus(); };

function esc(s) { return String(s).replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c])); }
function log(texto, classe) {
    const el = $('log');
    const perto = el.scrollHeight - el.scrollTop - el.clientHeight < 40;
    el.insertAdjacentHTML('beforeend', (classe ? '<span class="' + classe + '">' + esc(texto) + '</span>' : esc(texto)) + '\n');
    if (perto) el.scrollTop = el.scrollHeight;
}

// Monta as barras com os destinos marcados (os sites que vão junto com o Sistema entram também)
function iniciarProgresso(destinos) {
    linhas = {};
    $('sites').innerHTML = '';
    ETAPAS.filter(e => destinos.includes(e.destino) && !e.especial).forEach(e => criarLinha(e));
    $('progresso').hidden = false;
    atualizarGeral();
}
// Cada site tem duas barras: Local (cópia pras pastas de backup) em cima e FTP (envio pro site) embaixo
function criarLinha(e) {
    const el = document.createElement('div');
    el.className = 'site';
    const canal = (id, nome) => '<span class="canal-nome">' + nome + '</span><div class="barra" data-c="' + id + '"><div></div></div>' +
        '<span class="canal-status" data-c="' + id + '"></span><div class="canal-msg" data-c="' + id + '"></div>';
    el.innerHTML = '<div class="site-topo"><span></span><span class="chip"></span></div>' +
        '<div class="canais">' + canal('local', 'Local') + canal('ftp', 'FTP') + '</div>';
    el.querySelector('.site-topo span').textContent = e.nome;
    $('sites').appendChild(el);
    linhas[e.id] = { el, estado: 'aguardando' };
    const vazio = { estado: 'aguardando', pct: null, arquivos: 0, msg: '' };
    pintar(e.id, { estado: 'aguardando', local: vazio, ftp: vazio });
}
const ROTULOS_CANAL = {
    local: { aguardando: 'Aguardando', enviando: 'Copiando', ok: 'Copiado', erro: 'Erro', parcial: 'Parcial', pulado: 'Não copiado', cancelado: 'Cancelado' },
    ftp:   { aguardando: 'Aguardando', enviando: 'Enviando', ok: 'Enviado', erro: 'Erro', parcial: 'Parcial', pulado: 'Não enviado', cancelado: 'Cancelado' },
};
// ev: { estado, msg, local: {...}, ftp: {...} }; cada canal: { estado, pct (null = sem como medir), arquivos, arquivos_feitos, msg }
function pintar(id, ev) {
    const l = linhas[id];
    l.estado = ev.estado;
    l.el.className = 'site s-' + ev.estado;
    const chip = ev.estado === 'excecao' && ev.msg ? ROTULOS.excecao : (ev.estado === 'ok' ? 'Concluído' : ROTULOS[ev.estado]);
    l.el.querySelector('.chip').textContent = chip;
    ['local', 'ftp'].forEach(c => pintarCanal(l.el, c, ev[c], ev.estado === 'excecao' ? ev.msg : ''));
}
function pintarCanal(el, c, d, msgSite) {
    const medido = d.pct !== null && d.pct !== undefined;
    const barra = el.querySelector('.barra[data-c="' + c + '"]');
    const status = el.querySelector('.canal-status[data-c="' + c + '"]');
    const msg = el.querySelector('.canal-msg[data-c="' + c + '"]');
    let texto = ROTULOS_CANAL[c][d.estado];
    if (d.estado === 'enviando') {
        if (d.arquivos) texto += ' ' + d.arquivos_feitos + '/' + d.arquivos;
        if (medido) texto += ' · ' + d.pct + '%';
    }
    status.textContent = texto;
    let pct = 0;
    if (d.estado === 'enviando') pct = medido ? d.pct : 0;
    else if (d.estado !== 'aguardando') pct = 100;
    barra.className = 'barra c-' + d.estado + (d.estado === 'enviando' && !medido ? ' indeterminada' : '');
    status.className = 'canal-status c-' + d.estado;
    barra.firstChild.style.width = pct + '%';
    const m = d.msg || (c === 'ftp' ? msgSite : '');
    msg.className = 'canal-msg c-' + d.estado;
    msg.textContent = m;
    msg.title = m;
}
function etapa(ev) {
    if (!linhas[ev.id]) {
        // Site que não estava previsto: só mostra se realmente fez algo ou deu erro
        if (!['ok', 'erro', 'parcial'].includes(ev.estado)) return;
        criarLinha(ETAPAS.find(e => e.id === ev.id));
    }
    pintar(ev.id, ev);
    atualizarGeral();
}
function atualizarGeral() {
    const todas = Object.values(linhas);
    const feitas = todas.filter(l => FINAIS.includes(l.estado)).length;
    const erros = todas.filter(l => ['erro', 'parcial'].includes(l.estado)).length;
    const pct = todas.length ? feitas / todas.length * 100 : 0;
    $('geral-barra').style.width = pct + '%';
    $('geral-barra').style.background = erros ? 'var(--warn)' : (feitas === todas.length && todas.length ? 'var(--ok)' : '');
    $('geral-texto').textContent = feitas + ' de ' + todas.length + ' sites' + (erros ? ' · ' + erros + ' com erro' : '');
}
function encerrarProgresso(cancelado) {
    Object.keys(linhas).forEach(id => {
        const l = linhas[id];
        if (FINAIS.includes(l.estado)) return;
        const estado = cancelado ? 'cancelado' : 'pulado';
        const nada = { estado, pct: null, arquivos: 0, msg: '' };
        pintar(id, { estado, local: nada, ftp: Object.assign({}, nada, { msg: cancelado || l.estado !== 'aguardando' ? '' : 'O replicar.php não chegou nesse site' }) });
    });
    atualizarGeral();
}

function ocupado(sim) {
    $('analisar').disabled = sim;
    $('replicar').disabled = sim;
    $('cancelar').disabled = !sim;
}

async function executar(acao) {
    const caminho = $('caminho').value.trim();
    const info = await interpretar();
    if (!info) { if (!caminho) $('caminho').focus(); return; }

    const destinos = caixas().filter(c => c.checked).map(c => c.value);
    if (acao === 'replicar') {
        const nomes = caixas().filter(c => c.checked).map(c => c.nextElementSibling.firstChild.textContent);
        const arq = $('arquivo').value.trim() || 'último arquivo alterado';
        if (!nomes.length) { alert('Marque pelo menos um destino.'); return; }
        if (!confirm('Replicar "' + arq + '" para:\n\n' + nomes.join('\n') + '\n\nContinuar?')) return;
    }

    adicionarRecente(caminho);
    if (acao === 'replicar') iniciarProgresso(destinos); else $('progresso').hidden = true;
    $('log').textContent = '';
    log(acao === 'analisar' ? '=== Análise (nada será enviado) ===' : '=== Replicando ===', 'info');
    ocupado(true);
    controle = new AbortController();

    try {
        const resp = await chamar({ acao, caminho, arquivo: $('arquivo').value.trim(), destinos }, controle.signal);
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
                if (linha) tratar(JSON.parse(linha), acao);
            }
        }
    } catch (e) {
        log(e.name === 'AbortError' ? 'Cancelado.' : 'Erro: ' + e.message, 'erro');
        if (acao === 'replicar') encerrarProgresso(true);
    }
    ocupado(false);
    controle = null;
}

// Na análise o replicar.php é parado na confirmação ("s" pra sair) - essa parte não aparece no log,
// e no lugar dela vai um resumo do que seria replicado
let naConfirmacao = false;
let resumo = {};
function tratar(ev, acao) {
    switch (ev.t) {
        case 'out': {
            if (acao === 'analisar') {
                if (ev.s.startsWith('Verifique se está tudo ok')) { naConfirmacao = true; break; }
                if (naConfirmacao) break;
                const r = ev.s.match(/^(Diretorio completo|Pasta|Arquivo ou pasta a replicar|Tipo) = (.*)$/);
                if (r) resumo[r[1]] = r[2];
            }
            log(ev.s);
            const m = ev.s.match(/^Último arquivo alterado encontrado = (.+)$/);
            if (m && acao === 'analisar' && !$('arquivo').value.trim() && m[1] !== '[Nenhum encontrado]') {
                $('arquivo').placeholder = 'Vazio = ' + m[1] + ' (último alterado)';
            }
            break;
        }
        case 'resp': if (!(acao === 'analisar' && naConfirmacao)) log('  → ' + ev.s, 'resp'); break;
        case 'info': log(ev.s, 'info'); break;
        case 'aviso': log('⚠ ' + ev.s, 'aviso'); break;
        case 'erro': log(ev.s, 'erro'); break;
        case 'etapa': if (acao === 'replicar') etapa(ev); break;
        case 'fim':
            if (acao === 'replicar') {
                encerrarProgresso(false);
                if (ev.erro_sftp) log('\nCopie ou coloque o(s) arquivo(s) replicado(s) no git', 'aviso');
                log('\nTerminado.', ev.codigo === 0 ? 'ok' : 'erro');
            } else if (naConfirmacao) {
                log('\n✔ Análise concluída - nada foi copiado nem enviado.', 'ok');
                log('  Arquivo: ' + (resumo['Arquivo ou pasta a replicar'] || '?'), 'info');
                log('  Pasta do componente: ' + (resumo['Pasta'] || '?'), 'info');
                if (resumo['Tipo']) log('  Tipo: ' + resumo['Tipo'], 'info');
                log('  Se estiver tudo certo, marque os destinos e clique em Replicar.', 'info');
            } else {
                // Parou antes da confirmação: arquivo inexistente, na lista de exceções etc.
                log('\n✖ O replicar parou antes da confirmação - veja a mensagem acima.', 'erro');
            }
            naConfirmacao = false;
            resumo = {};
            break;
    }
}

$('analisar').onclick = () => executar('analisar');
$('replicar').onclick = () => executar('replicar');
$('cancelar').onclick = () => controle && controle.abort();

mostrarRecentes();
if ($('caminho').value) interpretar().then(info => { if (info) executar('analisar'); });
</script>
</body>
</html>
