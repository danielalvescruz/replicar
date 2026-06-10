<?php
// =====================================================================
// Script para executar comandos SQL em vários bancos Joomla
// Se a tabela não existir num site, copia do primeiro site da lista
// Suporta múltiplos comandos SQL separados por ;
// Uso: php executar_sql.php
// =====================================================================

// Lista de sites — adicione/remova conforme necessário
// IMPORTANTE: o PRIMEIRO site é usado como referência para copiar tabelas
// Campo 'usar': 'sim' = executa, 'perguntar' = pergunta antes, 'nao' = pula
$sites = array(
    array(
        'nome'     => 'Sacos Site (REFERÊNCIA)',
        'host'     => 'vps4.nunesti.com',
        'user'     => 'sacosbayplastic_nsite',
        'pass'     => '3Caras&1Fera#',
        'db'       => 'sacosbayplastic_nsite',
        'prefixo'  => 'nsite_',
        'usar'  => 'sim',
    ),
    array(
        'nome'     => 'Sacos Teste',
        'host'     => 'vps4.nunesti.com',
        'user'     => 'sacosbayplastic_nsite',
        'pass'     => '3Caras&1Fera#',
        'db'       => 'sacosbayplastic_teste',
        'prefixo'  => 'nsite_',
        'usar'  => 'sim',
    ),
   array(
        'nome'     => 'Clube Comary',
        'host'     => 'vps4.nunesti.com',
        'user'     => 'clubecomary',
        'pass'     => 'que#de2senha2019',
        'db'       => 'clubecomary',
        'prefixo'  => 'site_',
        'usar'  => 'sim',
    ),
    array(
        'nome'     => 'Clube Comary Teste',
        'host'     => 'vps4.nunesti.com',
        'user'     => 'comary_teste',
        'pass'     => '2Caras#&1Fera',
        'db'       => 'comary_teste',
        'prefixo'  => 'teste_',
        'usar'  => 'sim',
    ),
    array(
        'nome'     => 'CEERJ',
        'host'     => 'vps4.nunesti.com',
        'user'     => 'site',
        'pass'     => '3Caras&1Fera#',
        'db'       => 'site',
        'prefixo'  => 'site_',
        'usar'  => 'sim',
    ),
     array(
        'nome'     => 'Ifen',
        'host'     => 'vps4.nunesti.com',
        'user'     => 'ifen_nsite',
        'pass'     => '3CarasE1Fera#',
        'db'       => 'ifen_nsite',
        'prefixo'  => 'ifen_',
        'usar'  => 'sim',
    ),
     array(
        'nome'     => 'Edições Ifen',
        'host'     => 'vps4.nunesti.com',
        'user'     => 'edicoes',
        'pass'     => '3Caras&1Fera#',
        'db'       => 'edicoes_',
        'prefixo'  => 'edicoes_',
        'usar'  => 'sim',
    ),
    array(
        'nome'     => 'Aequor',
        'host'     => 'vps4.nunesti.com',
        'user'     => 'aequor',
        'pass'     => '3Caras&1Fera#',
        'db'       => 'aequor',
        'prefixo'  => 'aequor_',
        'usar'  => 'sim',
    ),
    array(
        'nome'     => 'Sistema',
        'host'     => 'vps4.nunesti.com',
        'user'     => 'sistema',
        'pass'     => '3CarasE1Fera#',
        'db'       => 'sistema_',
        'prefixo'  => 'sistema_',
        'usar'  => 'sim',
    ),
    array(
        'nome'     => 'Grupo New Smart Teste',
        'host'     => 'vps4.nunesti.com',
        'user'     => 'gnewteste_',
        'pass'     => '3Caras&1Fera#',
        'db'       => 'gnewteste_',
        'prefixo'  => 'gnewteste_',
        'usar'  => 'sim',
    ),
    array(
        'nome'     => 'Grupo New Smart',
        'host'     => 'vps4.nunesti.com',
        'user'     => 'gnewsmart',
        'pass'     => '3Caras&1Fera#',
        'db'       => 'gnewsmart_',
        'prefixo'  => 'gnewsmart_',
        'usar'  => 'sim',
    ),
    array(
        'nome'     => 'Ecorio Nsite',
        'host'     => 'vps4.nunesti.com',
        'user'     => 'ecorio_nsite',
        'pass'     => '3Caras&1Fera#',
        'db'       => 'ecorio_nsite',
        'prefixo'  => 'nsite_',
        'usar'  => 'sim',
    ),
    array(
        'nome'     => 'Ecorio Teste',
        'host'     => 'vps4.nunesti.com',
        'user'     => 'ecorioco_teste',
        'pass'     => 'que#de2senha2019',
        'db'       => 'ecorioco_teste',
        'prefixo'  => 'teste_',
        'usar'  => 'perguntar',
    ),
    array(
        'nome'     => 'Ecorio',
        'host'     => 'vps4.nunesti.com',
        'user'     => 'ecorioco_site',
        'pass'     => 'que#de2senha2019',
        'db'       => 'ecorioco_site',
        'prefixo'  => 'site_',
        'usar'  => 'perguntar',
    ),
    array(
        'nome'     => 'Fibrolar',
        'host'     => 'vps4.nunesti.com',
        'user'     => 'nsite',
        'pass'     => '3Caras&1Fera#',
        'db'       => 'nsite_',
        'prefixo'  => 'nsite_',
        'usar'  => 'sim',
    ),

     array(
        'nome'     => 'Limptek',
        'host'     => 'vps4.nunesti.com',
        'user'     => 'limptek',
        'pass'     => '3Caras&1Fera#',
        'db'       => 'limptek_',
        'prefixo'  => 'limptek_',
        'usar'  => 'sim',
    ),
);

// =====================================================================
// Solicita o SQL ao usuário
// =====================================================================

echo "\n========================================\n";
echo " Cole o SQL abaixo e finalize com uma\n";
echo " linha contendo apenas: fim\n";
echo "========================================\n";

$sqlOriginal = '';
$stdin = fopen('php://stdin', 'r');
while (($linha = fgets($stdin)) !== false) {
    if (trim($linha) === 'fim') {
        break;
    }
    $sqlOriginal .= $linha;
}
fclose($stdin);

$sqlOriginal = trim($sqlOriginal);

if ($sqlOriginal === '') {
    echo "\nNenhum SQL informado. Abortando.\n";
    exit(1);
}

// =====================================================================
// Detecta o prefixo e a tabela presentes no SQL colado
// =====================================================================
$prefixoDetectado = null;
$tabelaDetectada  = null; // nome COMPLETO da tabela (com prefixo), ex: nsite_mvas

// Tenta achar `prefixo_tabela` com crases
if (preg_match('/`([a-zA-Z0-9]+_)([a-zA-Z0-9_]+)`/', $sqlOriginal, $m)) {
    $prefixoDetectado = $m[1];
    $tabelaDetectada  = $m[1] . $m[2];
}
// Sem crases, após palavras-chave SQL
elseif (preg_match('/\b(?:TABLE|FROM|INTO|UPDATE|JOIN)\s+([a-zA-Z0-9]+_)([a-zA-Z0-9_]+)/i', $sqlOriginal, $m)) {
    $prefixoDetectado = $m[1];
    $tabelaDetectada  = $m[1] . $m[2];
}

if ($prefixoDetectado === null) {
    echo "\nNão foi possível detectar o prefixo no SQL. Abortando.\n";
    exit(1);
}

// Nome da tabela SEM prefixo (parte que é comum entre os sites)
$tabelaSemPrefixo = substr($tabelaDetectada, strlen($prefixoDetectado));

echo "\nPrefixo detectado : '$prefixoDetectado'\n";
echo "Tabela detectada  : '$tabelaDetectada' (base: '$tabelaSemPrefixo')\n";

// =====================================================================
// Confirmação
// =====================================================================
echo "\nSQL a ser executado (com prefixo substituído em cada site):\n";
echo "----------------------------------------\n";
echo $sqlOriginal . "\n";
echo "----------------------------------------\n";
echo "\nDeseja executar em " . count($sites) . " site(s)? (s/N): ";

$stdin = fopen('php://stdin', 'r');
$resp  = trim(fgets($stdin));
fclose($stdin);

if (strtolower($resp) !== 's') {
    echo "Cancelado pelo usuário.\n";
    exit(0);
}

// =====================================================================
// Função auxiliar: conecta num site
// =====================================================================
function conectarSite($site) {
    $conn = @new mysqli($site['host'], $site['user'], $site['pass'], $site['db']);
    if ($conn->connect_error) {
        return null;
    }
    $conn->set_charset('utf8');
    return $conn;
}

// =====================================================================
// Função auxiliar: pergunta s/N no terminal
// =====================================================================
function perguntarSimNao($mensagem) {
    echo $mensagem;
    $stdin = fopen('php://stdin', 'r');
    $resp  = trim(fgets($stdin));
    fclose($stdin);
    return strtolower($resp) === 's';
}

// =====================================================================
// Função auxiliar: divide um SQL em vários comandos por ';'
// Respeita ; que estejam dentro de strings 'simples' ou "duplas"
// =====================================================================
function dividirSql($sql) {
    $comandos    = array();
    $atual       = '';
    $dentroAspas = false;
    $aspa        = null;
    $tamanho     = strlen($sql);

    for ($i = 0; $i < $tamanho; $i++) {
        $char = $sql[$i];

        // Tratamento de aspas (entrada e saída)
        if (!$dentroAspas && ($char === "'" || $char === '"')) {
            $dentroAspas = true;
            $aspa = $char;
            $atual .= $char;
            continue;
        }
        if ($dentroAspas && $char === $aspa) {
            // Verifica escape: \' não fecha aspas
            $anterior = $i > 0 ? $sql[$i - 1] : '';
            if ($anterior !== '\\') {
                $dentroAspas = false;
                $aspa = null;
            }
            $atual .= $char;
            continue;
        }

        // Separador de comando — só fora de aspas
        if (!$dentroAspas && $char === ';') {
            $cmd = trim($atual);
            if ($cmd !== '') {
                $comandos[] = $cmd;
            }
            $atual = '';
            continue;
        }

        $atual .= $char;
    }

    // Último comando (sem ; final)
    $cmd = trim($atual);
    if ($cmd !== '') {
        $comandos[] = $cmd;
    }

    return $comandos;
}

// =====================================================================
// Pega o CREATE TABLE do primeiro site (site de referência)
// Só será usado se algum site precisar — buscamos sob demanda
// =====================================================================
$createTableReferencia = null; // cache do CREATE TABLE do site 1

function obterCreateTableReferencia($sites, $tabelaSemPrefixo, &$cache) {
    if ($cache !== null) {
        return $cache;
    }
    $siteRef       = $sites[0];
    $tabelaCompleta = $siteRef['prefixo'] . $tabelaSemPrefixo;

    $conn = conectarSite($siteRef);
    if ($conn === null) {
        return false;
    }

    $res = $conn->query("SHOW CREATE TABLE `$tabelaCompleta`");
    if ($res === false) {
        $conn->close();
        return false;
    }

    $row = $res->fetch_assoc();
    $conn->close();

    // O comando vem na coluna "Create Table"
    $cache = $row['Create Table'];
    return $cache;
}

// =====================================================================
// Execução
// =====================================================================
echo "\n========================================\n";
echo " Executando em " . count($sites) . " site(s)\n";
echo "========================================\n\n";

$sucesso  = 0;
$falha    = 0;
$criadas  = 0;
$pulados  = 0;

foreach ($sites as $indice => $site) {
    echo "\n----------------------------------------\n";
    echo "-> [{$site['nome']}]\n";

    // Define o flag 'usar' (default 'sim' se não existir)
    $usar = isset($site['usar']) ? strtolower($site['usar']) : 'sim';

    // Se for 'nao', pula sem perguntar
    if ($usar === 'nao') {
        echo "   PULADO (configurado como 'nao')\n";
        $pulados++;
        continue;
    }

    // SQL com o prefixo trocado pelo do site atual
    $sqlCompleto = str_replace($prefixoDetectado, $site['prefixo'], $sqlOriginal);

    // Divide em comandos individuais (separados por ;)
    $comandos = dividirSql($sqlCompleto);

    // Mostra os comandos que serão executados neste site
    echo "   SQL a executar (" . count($comandos) . " comando(s)):\n";
    foreach ($comandos as $i => $cmd) {
        $num = $i + 1;
        echo "   [$num] " . str_replace("\n", "\n       ", $cmd) . "\n";
    }

    // ============================================================
    // CONFIRMAÇÃO PRÉ-EXECUÇÃO (TEMPORÁRIO - comentar depois)
    // Pergunta s/N antes de cada site, independente do 'usar'
    // ============================================================
    if (!perguntarSimNao("   Executar neste site? (s/N): ")) {
        echo "   PULADO pelo usuário.\n";
        $pulados++;
        continue;
    }
    // ============================================================
    // FIM da confirmação temporária
    // ============================================================

    // Se for 'perguntar', confirma antes
    // OBS: enquanto o bloco acima estiver ativo, este 'perguntar' fica redundante
    if ($usar === 'perguntar') {
        if (!perguntarSimNao("   Confirmar execução (site marcado como 'perguntar')? (s/N): ")) {
            echo "   PULADO pelo usuário.\n";
            $pulados++;
            continue;
        }
    }

    // Conecta
    $conn = conectarSite($site);
    if ($conn === null) {
        echo "   ERRO de conexão\n";
        $falha++;
        continue;
    }

    // Executa cada comando individualmente
    $erroNesteSite = false;
    foreach ($comandos as $i => $sql) {
        $num = $i + 1;
        echo "   [$num] Executando ... ";

        $resultado = $conn->query($sql);

        // Se deu erro 1146 (tabela não existe), tenta copiar do site de referência
        if ($resultado === false && $conn->errno === 1146) {

            // Não dá pra copiar do próprio site de referência
            if ($indice === 0) {
                echo "ERRO: tabela não existe no site de referência. Pulando comando.\n";
                $erroNesteSite = true;
                continue;
            }

            echo "tabela não existe, criando a partir do site de referência ... ";

            // Pega o CREATE TABLE do primeiro site
            $createSql = obterCreateTableReferencia($sites, $tabelaSemPrefixo, $createTableReferencia);

            if ($createSql === false) {
                echo "ERRO ao obter CREATE TABLE do site de referência\n";
                $erroNesteSite = true;
                continue;
            }

            // Troca o prefixo no CREATE TABLE
            $createSqlAjustado = str_replace(
                '`' . $sites[0]['prefixo'] . $tabelaSemPrefixo . '`',
                '`' . $site['prefixo'] . $tabelaSemPrefixo . '`',
                $createSql
            );

            // Cria a tabela
            if ($conn->query($createSqlAjustado) === false) {
                echo "ERRO ao criar tabela: {$conn->error}\n";
                $erroNesteSite = true;
                continue;
            }

            $criadas++;
            echo "criada. Reexecutando comando ... ";

            // Reexecuta o comando
            $resultado = $conn->query($sql);
        }

        if ($resultado === true || $resultado !== false) {
            echo "OK\n";
        } else {
            echo "ERRO: {$conn->error}\n";
            $erroNesteSite = true;
        }
    }

    if ($erroNesteSite) {
        $falha++;
    } else {
        $sucesso++;
    }

    $conn->close();
}

echo "\n========================================\n";
echo " Resultado: $sucesso sucesso(s), $falha falha(s), $pulados pulado(s)\n";
if ($criadas > 0) {
    echo " Tabelas criadas a partir da referência: $criadas\n";
}
echo "========================================\n\n";