<?php
// Execução no SERVIDOR DO RICARDO (incluído pelo api.php, no escopo global): o site não tem proc_open,
// então o replicar.php / replicar_bd.php é carregado aqui dentro, como se fosse rodado pelo terminal.
// Tudo que ele escreve passa por WebSaida::capturar(), que transforma cada linha em eventos JSON e
// manda na hora pra página. As perguntas são respondidas pelos ganchos replicar_web_resposta() e
// replicar_web_sql(), que o replicar.php / replicar_bd.php chamam quando existem.
if (!defined('MODO_SERVIDOR') || !MODO_SERVIDOR) {
    http_response_code(404);
    exit;
}

// Recebe tudo que o replicar escreve e devolve, no lugar, os eventos JSON de cada linha completa
class WebSaida
{
    public static $buffer = '';
    public static $aoLinha = null;   // function ($linha) -> lista de eventos
    public static $ativa = false;    // true depois do ob_start (antes disso os eventos saem direto)

    public static function iniciar()
    {
        self::$ativa = true;
        ob_start(array('WebSaida', 'capturar'), 1);
    }

    public static function capturar($texto, $fase)
    {
        self::$buffer .= $texto;
        $saida = '';
        while (($pos = strpos(self::$buffer, "\n")) !== false) {
            $linha = rtrim(substr(self::$buffer, 0, $pos), "\r");
            self::$buffer = substr(self::$buffer, $pos + 1);
            $saida .= self::converter($linha);
        }
        if (($fase & PHP_OUTPUT_HANDLER_FINAL) && self::$buffer !== '') {
            $saida .= self::converter(self::$buffer);
            self::$buffer = '';
        }
        return $saida;
    }

    private static function converter($linha)
    {
        $saida = '';
        // Marcas que a própria página escreve: resposta dada a uma pergunta, aviso, erro e fim.
        // A pergunta pode estar na mesma linha (ex: "Executar neste site? (s/N): @@RESPOSTA s")
        if (preg_match('/^(.*?)@@(RESPOSTA|AVISO|ERRO|FIM)(?: (.*))?$/su', $linha, $m)) {
            if ($m[1] !== '') {
                $saida .= self::converter($m[1]);
            }
            $resto = isset($m[3]) ? $m[3] : '';
            if ($m[2] === 'RESPOSTA') {
                return $saida . eventoJson(array('t' => 'resp', 's' => $resto === '' ? '[Enter]' : $resto));
            }
            if ($m[2] === 'AVISO') {
                return $saida . eventoJson(array('t' => 'aviso', 's' => $resto));
            }
            if ($m[2] === 'ERRO') {
                return $saida . eventoJson(array('t' => 'erro', 's' => $resto));
            }
            // FIM
            $ev = WebReplicar::$progresso ? WebReplicar::$progresso->fim(false) : null;
            if ($ev) {
                $saida .= eventoJson($ev);
            }
            return $saida . eventoJson(array('t' => 'fim', 'codigo' => 0, 'erro_sftp' => WebReplicar::$erro_sftp));
        }
        foreach (call_user_func(self::$aoLinha, $linha) as $ev) {
            $saida .= eventoJson($ev);
        }
        return $saida;
    }
}

// Quando o replicar termina (inclusive com exit() no meio dele), fecha a última barra e avisa a página
register_shutdown_function(function () {
    if (!WebSaida::$ativa) {
        return;
    }
    $erro = error_get_last();
    if ($erro && in_array($erro['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        echo "\n@@ERRO Erro no replicar: " . $erro['message'] . ' (' . basename($erro['file']) . ':' . $erro['line'] . ")\n";
    }
    echo "\n@@FIM\n";
});

// Faz o replicar_class.php escrever o progresso dos envios (@@PROGRESSO)
putenv('REPLICAR_PROGRESSO=1');

// Ganchos chamados pelo replicar.php / replicar_bd.php no lugar de ler o terminal
function replicar_web_resposta($pergunta)
{
    if (WebReplicar::$acao === 'banco') {
        $resposta = responderBanco($pergunta, WebReplicar::$marcados, WebReplicar::$progresso->atualId());
    } else {
        $aviso = '';
        $resposta = responder($pergunta, WebReplicar::$acao, WebReplicar::$arquivo, WebReplicar::$marcados, $aviso);
        if ($aviso) {
            echo '@@AVISO ' . $aviso . "\n";
        }
    }
    if ($resposta === null) {
        $resposta = '';
    }
    echo '@@RESPOSTA ' . $resposta . "\n";
    return $resposta;
}

function replicar_web_sql()
{
    return WebReplicar::$sql;
}

if (WebReplicar::$acao === 'banco') {
    WebSaida::$aoLinha = function ($linha) {
        $eventos = WebReplicar::$progresso->linha($linha);
        $eventos[] = array('t' => 'out', 's' => $linha);
        return $eventos;
    };
    chdir(dirname(REPLICAR_BD_LOCAL));
    WebSaida::iniciar();
    include REPLICAR_BD_LOCAL;
    exit;
}

WebSaida::$aoLinha = function ($linha) {
    if (preg_match('/Erro: login SFTP falhou|Erro: chave SSH não encontrada/', $linha)) {
        WebReplicar::$erro_sftp = true;
    }
    $mostrar = true;
    $eventos = WebReplicar::$progresso->linha($linha, $mostrar);
    if ($mostrar) {
        $eventos[] = array('t' => 'out', 's' => $linha);
    }
    return $eventos;
};
chdir($web_info['pasta']);
unset($web_info);
WebSaida::iniciar();
include REPLICAR_PHP_LOCAL;
exit;
