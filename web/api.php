<?php
// Endpoints das páginas:
//   acao=interpretar  -> descobre pasta e arquivo a partir do caminho colado
//   acao=analisar     -> roda o replicar.php e para na confirmação (não envia nada)
//   acao=replicar     -> roda o replicar.php respondendo as perguntas com os destinos marcados
//   acao=banco        -> roda o replicar_bd.php com o SQL colado nos sites marcados
//   acao=sites_ler / sites_salvar -> lê e grava a lista de sites (tela "Sites")
// As ações que rodam o replicar mandam a saída aos poucos (uma linha JSON por evento) pra aparecer ao vivo.
// Como o replicar roda depende de onde a página está (ver lib.php): api_local.php ou api_servidor.php.
require __DIR__ . '/lib.php';

// Só aceita chamadas da própria página: outro site aberto no navegador não consegue mandar esse
// cabeçalho sem uma liberação de CORS que não damos.
if (valor($_SERVER, 'HTTP_X_REPLICAR', '') !== '1' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit('Acesso negado');
}

function responderJson($dados)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!logado()) {
    http_response_code(401);
    responderJson(array('erro' => 'login'));
}
if (MODO_SERVIDOR) {
    // Libera a sessão: uma replicação demorada não trava as outras abas
    session_write_close();
}

// Estado desta chamada fica numa classe: no servidor o replicar.php roda dentro desta página e
// as variáveis globais dele não podem se misturar com as daqui
class WebReplicar
{
    public static $entrada = array();
    public static $acao = '';
    public static $marcados = array();
    public static $arquivo = '';
    public static $sql = '';
    public static $progresso = null;
    public static $erro_sftp = false;
}

WebReplicar::$entrada = json_decode(file_get_contents('php://input'), true) ?: array();
WebReplicar::$acao = valor(WebReplicar::$entrada, 'acao', '');

if (WebReplicar::$acao === 'sites_ler') {
    responderJson(array('sites' => lerSites()));
}
if (WebReplicar::$acao === 'sites_salvar') {
    $erros = array();
    $lista = validarSites(valor(WebReplicar::$entrada, 'sites', array()), $erros);
    if ($erros) {
        responderJson(array('ok' => false, 'erros' => $erros));
    }
    if (!salvarSites($lista)) {
        responderJson(array('ok' => false, 'erros' => array('Não foi possível gravar o ' . ARQUIVO_SITES)));
    }
    responderJson(array('ok' => true, 'sites' => $lista));
}
if (WebReplicar::$acao === 'interpretar') {
    responderJson(interpretarCaminho(valor(WebReplicar::$entrada, 'caminho', '')));
}
if (!in_array(WebReplicar::$acao, array('analisar', 'replicar', 'banco'), true)) {
    http_response_code(400);
    exit('Ação inválida');
}

// ---------------------------------------------------------------------------------------------
// Saída ao vivo
// ---------------------------------------------------------------------------------------------
set_time_limit(0);
ignore_user_abort(false);
header('Content-Type: application/x-ndjson; charset=utf-8');
header('Cache-Control: no-cache, no-transform');
header('X-Accel-Buffering: no');
while (ob_get_level()) {
    ob_end_flush();
}
ob_implicit_flush(true);

function eventoJson($dados)
{
    return json_encode($dados, JSON_UNESCAPED_UNICODE) . "\n";
}

function evento($dados)
{
    echo eventoJson($dados);
    flush();
}

WebReplicar::$marcados = array_values(array_filter((array) valor(WebReplicar::$entrada, 'destinos', array()), 'is_string'));

if (WebReplicar::$acao === 'banco') {
    WebReplicar::$sql = trim((string) valor(WebReplicar::$entrada, 'sql', ''));
    if (WebReplicar::$sql === '') {
        evento(array('t' => 'erro', 's' => 'Cole o SQL a executar.'));
        exit;
    }
    WebReplicar::$progresso = new ProgressoBanco();
    $web_info = null;
} else {
    $web_info = interpretarCaminho(valor(WebReplicar::$entrada, 'caminho', ''));
    if (!empty($web_info['erro'])) {
        evento(array('t' => 'erro', 's' => $web_info['erro']));
        exit;
    }
    WebReplicar::$arquivo = trim(valor(WebReplicar::$entrada, 'arquivo', ''));
    if (WebReplicar::$arquivo === '') {
        WebReplicar::$arquivo = $web_info['arquivo'];
    }
    WebReplicar::$progresso = new Progresso();
    evento(array('t' => 'info', 's' => $web_info['onde'] . ' - pasta: ' . $web_info['pasta']));
}

// Continua no escopo global (no servidor o replicar.php é incluído lá dentro e precisa disso)
if (MODO_SERVIDOR) {
    include __DIR__ . '/api_servidor.php';
} else {
    include __DIR__ . '/api_local.php';
}
