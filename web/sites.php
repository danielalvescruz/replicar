<?php
require __DIR__ . '/lib.php';
exigirLogin();
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sites do Replicar</title>
<link rel="stylesheet" href="estilo.css">
<style>
.topo-sites { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 14px; }
.topo-sites .espaco { flex: 1; }
#status-salvar { font-size: 13px; color: var(--muted); }
#status-salvar.pendente { color: var(--warn); }
#erros { color: var(--err); font-size: 14px; }
#erros:empty { display: none; }
.lista-sites { display: grid; gap: 8px; }
.item { background: var(--card); border: 1px solid var(--border); border-radius: 10px; }
.item.inativo .item-nome, .item.inativo .selo { opacity: .5; }
.item-topo { display: flex; align-items: center; gap: 8px; padding: 10px 12px; flex-wrap: wrap; }
.item-topo button { padding: 4px 10px; font-size: 13px; }
.mover { display: flex; gap: 2px; }
.mover button { padding: 2px 7px; }
.item-nome { font-weight: 600; flex: 1; min-width: 140px; }
.selo { font-size: 11px; padding: 1px 7px; border-radius: 99px; border: 1px solid var(--border); color: var(--muted); white-space: nowrap; }
.selo.arq { color: var(--accent); border-color: var(--accent); }
.selo.bd { color: var(--ok); border-color: var(--ok); }
.remover { color: var(--err); }
.publicar { border-radius: 99px; font-size: 12px !important; padding: 2px 10px !important; }
.publicar.sim { color: var(--ok); border-color: var(--ok); }
.publicar.nao { color: var(--muted); }
.publicar.sim::before { content: "● "; } .publicar.nao::before { content: "○ "; }
.form { border-top: 1px solid var(--border); padding: 12px; display: grid; gap: 14px; }
.form[hidden] { display: none; }
fieldset { border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px 12px; margin: 0; }
legend { padding: 0 6px; font-weight: 600; font-size: 14px; }
.campos { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px 14px; margin-top: 8px; }
.campos[hidden] { display: none; }
.campo label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 3px; }
.campo input[type=text], .campo input[type=password], .campo select { width: 100%; }
.campo.largo { grid-column: 1 / -1; }
.check { display: flex; gap: 8px; align-items: center; font-size: 14px; cursor: pointer; }
.senha { display: flex; gap: 4px; }
.senha input { flex: 1; min-width: 0; }
.senha button { padding: 4px 8px; font-size: 12px; }
</style>
</head>
<body>
<main>
    <?php $pagina = 'sites'; include __DIR__ . '/menu.php'; ?>

    <div class="topo-sites">
        <button id="adicionar">+ Adicionar site</button>
        <span class="espaco"></span>
        <span id="status-salvar"></span>
        <button id="salvar" class="principal" disabled>Salvar</button>
    </div>
    <div class="card" id="erros"></div>
    <div class="dica" style="margin-bottom:10px">A ordem da lista é a ordem em que o replicar roda. Tudo aqui vale também para o replicar pelo terminal <?= MODO_SERVIDOR ? 'deste servidor' : 'desta máquina' ?> (<code><?= basename(ARQUIVO_SITES) ?></code>). Cada lugar tem a sua lista.</div>

    <div class="lista-sites" id="lista"></div>
</main>

<script>
const $ = id => document.getElementById(id);
let sites = [];
let alterado = false;
let abertos = new Set();

async function api(corpo) {
    const r = await fetch('api.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Replicar': '1' }, body: JSON.stringify(corpo) });
    if (r.status === 401) { location.href = 'login.php'; throw new Error('Sessão expirada'); }
    return r.json();
}

function marcarAlterado() {
    alterado = true;
    $('salvar').disabled = false;
    $('status-salvar').textContent = 'Alterações não salvas';
    $('status-salvar').className = 'pendente';
}
window.addEventListener('beforeunload', e => { if (alterado) { e.preventDefault(); e.returnValue = ''; } });

const novoArquivos = () => ({ perguntar: true, junto_com: '', protocolo: 'ftp', host: '', usuario: '', senha: '', pasta_remota: '',
    copia_local: 'nenhuma', pasta_cliente: '', alias: '', ignorar_excecoes: false, somente_tipo: '' });
const novoBanco = () => ({ host: '', usuario: '', senha: '', banco: '', prefixo: '', usar: 'sim', referencia: false });

function esc(s) { return String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }

function campoTexto(rotulo, caminho, valor, extra = '') {
    return '<div class="campo ' + extra + '"><label>' + rotulo + '</label><input type="text" class="pequeno" data-campo="' + caminho + '" value="' + esc(valor) + '"></div>';
}
function campoSenha(rotulo, caminho, valor) {
    return '<div class="campo"><label>' + rotulo + '</label><div class="senha"><input type="password" data-campo="' + caminho + '" value="' + esc(valor) +
        '"><button type="button" data-ver>ver</button></div></div>';
}
function campoSelect(rotulo, caminho, valor, opcoes) {
    return '<div class="campo"><label>' + rotulo + '</label><select data-campo="' + caminho + '">' +
        opcoes.map(o => '<option value="' + esc(o[0]) + '"' + (o[0] === valor ? ' selected' : '') + '>' + esc(o[1]) + '</option>').join('') + '</select></div>';
}
function campoCheck(rotulo, caminho, valor, extra = '') {
    return '<div class="campo ' + extra + '"><label class="check"><input type="checkbox" data-campo="' + caminho + '"' + (valor ? ' checked' : '') + '> ' + rotulo + '</label></div>';
}

function formulario(s, i) {
    const a = s.arquivos, b = s.banco;
    const perguntam = sites.filter((o, j) => j !== i && o.id && o.arquivos && o.arquivos.perguntar);
    const quando = a ? (a.perguntar ? '' : a.junto_com) : '';
    let h = '<div class="campos" style="margin-top:0">' + campoTexto('Nome', 'nome', s.nome, 'largo') + '</div>';

    h += '<fieldset><legend><label class="check"><input type="checkbox" data-secao="arquivos"' + (a ? ' checked' : '') + '> Envio de arquivos (FTP/SFTP)</label></legend>';
    h += '<div class="campos"' + (a ? '' : ' hidden') + '>';
    if (a) {
        h += campoSelect('Quando enviar', 'arquivos.quando', quando, [['', 'Perguntar antes'], ...perguntam.map(o => [o.id, 'Junto com ' + o.nome])]);
        h += campoSelect('Protocolo', 'arquivos.protocolo', a.protocolo, [['ftp', 'FTP'], ['sftp', 'SFTP']]);
        h += campoTexto('Servidor', 'arquivos.host', a.host);
        h += campoTexto('Usuário', 'arquivos.usuario', a.usuario);
        h += campoSenha('Senha', 'arquivos.senha', a.senha);
        h += campoTexto('Pasta do site no servidor', 'arquivos.pasta_remota', a.pasta_remota, 'largo');
        h += campoSelect('Cópia local', 'arquivos.copia_local', a.copia_local, [['nenhuma', 'Nenhuma'], ['cliente', 'Pasta do cliente'], ['sistema', 'Pasta Sistema']]);
        if (a.copia_local === 'cliente') h += campoTexto('Pasta do cliente (em Aguiarsoft Ricardo)', 'arquivos.pasta_cliente', a.pasta_cliente);
        h += campoTexto('Nome nas exceções', 'arquivos.alias', a.alias);
        h += campoTexto('Só para o tipo (opcional)', 'arquivos.somente_tipo', a.somente_tipo);
        h += campoCheck('Ignorar a lista de exceções', 'arquivos.ignorar_excecoes', a.ignorar_excecoes);
    }
    h += '</div></fieldset>';

    h += '<fieldset><legend><label class="check"><input type="checkbox" data-secao="banco"' + (b ? ' checked' : '') + '> Banco de dados (MySQL)</label></legend>';
    h += '<div class="campos"' + (b ? '' : ' hidden') + '>';
    if (b) {
        h += campoTexto('Servidor', 'banco.host', b.host);
        h += campoTexto('Usuário', 'banco.usuario', b.usuario);
        h += campoSenha('Senha', 'banco.senha', b.senha);
        h += campoTexto('Banco', 'banco.banco', b.banco);
        h += campoTexto('Prefixo das tabelas', 'banco.prefixo', b.prefixo);
        h += campoSelect('Usar', 'banco.usar', b.usar, [['sim', 'Sim (marcado por padrão)'], ['perguntar', 'Perguntar (desmarcado por padrão)'], ['nao', 'Não (desligado)']]);
        h += campoCheck('Referência (copia tabelas que faltam)', 'banco.referencia', b.referencia);
    }
    h += '</div></fieldset>';
    return h;
}

function desenhar() {
    const lista = $('lista');
    lista.innerHTML = '';
    sites.forEach((s, i) => {
        const el = document.createElement('div');
        el.className = 'item' + (s.ativo ? '' : ' inativo');
        let selos = '';
        if (s.arquivos) selos += '<span class="selo arq">' + (s.arquivos.protocolo === 'sftp' ? 'SFTP' : 'FTP') + (s.arquivos.perguntar ? '' : ' · junto') + '</span>';
        if (s.banco) selos += '<span class="selo bd">Banco' + (s.banco.referencia ? ' · referência' : '') + '</span>';
        el.innerHTML = '<div class="item-topo"><span class="mover"><button data-acao="subir" title="Subir"' + (i === 0 ? ' disabled' : '') + '>↑</button>' +
            '<button data-acao="descer" title="Descer"' + (i === sites.length - 1 ? ' disabled' : '') + '>↓</button></span>' +
            '<button data-acao="publicar" class="publicar ' + (s.ativo ? 'sim' : 'nao') + '" title="Clique para ' + (s.ativo ? 'despublicar' : 'publicar') + ' (salva na hora)">' +
            (s.ativo ? 'Publicado' : 'Despublicado') + '</button>' +
            '<span class="item-nome">' + esc(s.nome || '(sem nome)') + '</span>' + selos +
            '<button data-acao="editar">' + (abertos.has(s) ? 'Fechar' : 'Editar') + '</button><button data-acao="remover" class="remover">Remover</button></div>' +
            '<div class="form"' + (abertos.has(s) ? '' : ' hidden') + '>' + (abertos.has(s) ? formulario(s, i) : '') + '</div>';

        el.querySelector('.item-topo').addEventListener('click', ev => {
            const acao = ev.target.dataset.acao;
            if (!acao) return;
            if (acao === 'subir' || acao === 'descer') {
                const j = acao === 'subir' ? i - 1 : i + 1;
                [sites[i], sites[j]] = [sites[j], sites[i]];
                marcarAlterado();
            } else if (acao === 'publicar') {
                // Igual ao Joomla: publicar/despublicar já salva
                s.ativo = !s.ativo;
                salvar().then(ok => { if (!ok) { s.ativo = !s.ativo; desenhar(); } });
            } else if (acao === 'editar') {
                abertos.has(s) ? abertos.delete(s) : abertos.add(s);
            } else if (acao === 'remover') {
                if (!confirm('Remover o site "' + (s.nome || 'sem nome') + '"?\n\nSe quiser só parar de usar, clique em "Publicado" para despublicar.')) return;
                sites.splice(i, 1);
                marcarAlterado();
            }
            desenhar();
        });

        const form = el.querySelector('.form');
        form.addEventListener('input', ev => atualizarCampo(s, ev.target, false));
        form.addEventListener('change', ev => atualizarCampo(s, ev.target, true));
        form.addEventListener('click', ev => {
            if (ev.target.dataset.ver === undefined) return;
            const inp = ev.target.previousElementSibling;
            inp.type = inp.type === 'password' ? 'text' : 'password';
            ev.target.textContent = inp.type === 'password' ? 'ver' : 'ocultar';
        });
        lista.appendChild(el);
    });
}

// Grava no objeto do site o que foi digitado. "redesenhar" = mudança que muda o formulário (ex: seções, selects)
function atualizarCampo(s, alvo, redesenhar) {
    if (alvo.dataset.secao) {
        if (!redesenhar) return;
        const sec = alvo.dataset.secao;
        s[sec] = alvo.checked ? (sec === 'arquivos' ? novoArquivos() : novoBanco()) : null;
        marcarAlterado();
        desenhar();
        return;
    }
    const caminho = alvo.dataset.campo;
    if (!caminho) return;
    const valor = alvo.type === 'checkbox' ? alvo.checked : alvo.value;
    if (alvo.type === 'checkbox' && !redesenhar) return;
    const [a, b] = caminho.split('.');
    if (!b) {
        s[a] = valor;
    } else if (b === 'quando') {
        s.arquivos.perguntar = valor === '';
        s.arquivos.junto_com = valor;
    } else {
        s[a][b] = valor;
        // Só um site pode ser a referência do banco
        if (a === 'banco' && b === 'referencia' && valor) sites.forEach(o => { if (o !== s && o.banco) o.banco.referencia = false; });
    }
    marcarAlterado();
    if (redesenhar && (alvo.tagName === 'SELECT' || alvo.type === 'checkbox')) desenhar();
    else if (a === 'nome') alvo.closest('.item').querySelector('.item-nome').textContent = valor || '(sem nome)';
}

$('adicionar').onclick = () => {
    const s = { id: '', nome: '', ativo: true, arquivos: novoArquivos(), banco: null };
    sites.push(s);
    abertos.add(s);
    marcarAlterado();
    desenhar();
    const campos = document.querySelectorAll('[data-campo="nome"]');
    campos[campos.length - 1].focus();
};

$('salvar').onclick = () => salvar();

async function salvar() {
    $('salvar').disabled = true;
    $('status-salvar').textContent = 'Salvando…';
    const r = await api({ acao: 'sites_salvar', sites });
    if (!r.ok) {
        $('erros').innerHTML = '<strong>Não foi salvo:</strong><br>' + r.erros.map(esc).join('<br>');
        $('status-salvar').textContent = 'Alterações não salvas';
        $('salvar').disabled = false;
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return false;
    }
    $('erros').innerHTML = '';
    // Mantém abertos os mesmos sites depois de recarregar a lista (agora com os ids gerados)
    const abertosIdx = sites.map((s, i) => abertos.has(s) ? i : -1).filter(i => i >= 0);
    sites = r.sites;
    abertos = new Set(abertosIdx.map(i => sites[i]));
    alterado = false;
    $('status-salvar').textContent = 'Salvo';
    $('status-salvar').className = '';
    desenhar();
    return true;
}

api({ acao: 'sites_ler' }).then(r => { sites = r.sites; desenhar(); });
</script>
</body>
</html>
