<?php
// Execução na MÁQUINA DO DANIEL (incluído pelo api.php): roda o replicar.php / replicar_bd.php como
// processo separado (proc_open) - local, ou no servidor do Ricardo via SSH - e responde as perguntas
// pela entrada padrão, como se fosse alguém digitando no terminal.
if (!defined('MODO_SERVIDOR') || MODO_SERVIDOR) {
    http_response_code(404);
    exit;
}

// Roda o script e chama $aoLinha pra cada linha de saída. $aoLinha devolve a resposta a mandar
// (ou null). Perguntas que terminam sem quebra de linha, tipo "(s/N): ", também chegam como linha.
// Retorna array(codigo de saída, cancelado).
function rodar($cmd, $cwd, $entrada_inicial, $aoLinha)
{
    // REPLICAR_PROGRESSO=1 faz o replicar_class.php escrever o progresso dos envios
    $ambiente = array_merge(getenv(), array('REPLICAR_PROGRESSO' => '1'));
    $proc = proc_open($cmd, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('redirect', 1)), $pipes, $cwd, $ambiente);
    if (!is_resource($proc)) {
        evento(array('t' => 'erro', 's' => 'Não foi possível iniciar o script.'));
        return array(-1, false);
    }
    if ($entrada_inicial !== '') {
        fwrite($pipes[0], $entrada_inicial);
        fflush($pipes[0]);
    }
    stream_set_blocking($pipes[1], false);

    $buffer = '';
    $ultimo_sinal = time();
    $ultima_saida = time();
    $cancelado = false;

    $tratar = function ($linha) use ($aoLinha, $pipes) {
        $resposta = $aoLinha($linha);
        if ($resposta !== null) {
            fwrite($pipes[0], $resposta . "\n");
            fflush($pipes[0]);
            evento(array('t' => 'resp', 's' => $resposta === '' ? '[Enter]' : $resposta));
        }
    };

    while (true) {
        $ler = array($pipes[1]);
        $w = null;
        $e = null;
        if (stream_select($ler, $w, $e, 1)) {
            $pedaco = fread($pipes[1], 8192);
            if ($pedaco === '' || $pedaco === false) {
                if (feof($pipes[1])) {
                    break;
                }
            } else {
                $ultima_saida = time();
                $buffer .= $pedaco;
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $linha = rtrim(substr($buffer, 0, $pos), "\r");
                    $buffer = substr($buffer, $pos + 1);
                    $tratar($linha);
                }
                if (preg_match('/\(s\/N\):\s*$/', $buffer)) {
                    $linha = $buffer;
                    $buffer = '';
                    $tratar($linha);
                }
            }
        }

        // Mantém a conexão viva e descobre se a página foi fechada / cancelada
        if (time() - $ultimo_sinal >= 5) {
            evento(array('t' => 'ping'));
            $ultimo_sinal = time();
            if (connection_aborted()) {
                proc_terminate($proc);
                $cancelado = true;
                break;
            }
        }
        // Trava de segurança: 10 minutos sem nenhuma saída
        if (time() - $ultima_saida > 600) {
            evento(array('t' => 'erro', 's' => 'Sem resposta do script há 10 minutos, processo encerrado.'));
            proc_terminate($proc);
            break;
        }
    }

    if ($buffer !== '') {
        $aoLinha($buffer);
    }
    fclose($pipes[0]);
    fclose($pipes[1]);
    return array(proc_close($proc), $cancelado);
}

if (WebReplicar::$acao === 'banco') {
    // O replicar_bd.php lê o SQL até uma linha "fim"
    $sql = preg_replace('/^\s*fim\s*$/mi', '', WebReplicar::$sql);
    list($codigo, $cancelado) = rodar(array(PHP_BINARY, REPLICAR_BD_LOCAL), dirname(REPLICAR_BD_LOCAL), $sql . "\nfim\n",
        function ($linha) {
            foreach (WebReplicar::$progresso->linha($linha) as $ev) {
                evento($ev);
            }
            evento(array('t' => 'out', 's' => $linha));
            return responderBanco($linha, WebReplicar::$marcados, WebReplicar::$progresso->atualId());
        });
    if ($ev = WebReplicar::$progresso->fim($cancelado)) {
        evento($ev);
    }
    evento(array('t' => 'fim', 'codigo' => $codigo));
    exit;
}

list($cmd, $cwd) = comandoReplicar($web_info);
list($codigo, $cancelado) = rodar($cmd, $cwd, '', function ($linha) {
    if (preg_match('/Erro: login SFTP falhou|Erro: chave SSH não encontrada/', $linha)) {
        WebReplicar::$erro_sftp = true;
    }
    $mostrar = true;
    foreach (WebReplicar::$progresso->linha($linha, $mostrar) as $ev) {
        evento($ev);
    }
    if (!$mostrar) {
        return null;
    }
    evento(array('t' => 'out', 's' => $linha));

    $aviso = '';
    $resposta = responder($linha, WebReplicar::$acao, WebReplicar::$arquivo, WebReplicar::$marcados, $aviso);
    if ($aviso) {
        evento(array('t' => 'aviso', 's' => $aviso));
    }
    return $resposta;
});

if ($ev = WebReplicar::$progresso->fim($cancelado)) {
    evento($ev);
}
evento(array('t' => 'fim', 'codigo' => $codigo, 'erro_sftp' => WebReplicar::$erro_sftp));
exit;
