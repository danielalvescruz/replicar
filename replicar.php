<?php
error_reporting(E_ERROR | E_PARSE);

if (!defined('STDIN')) {
    define('STDIN', fopen('php://stdin', 'r'));
}

function prompt($msg)
{
    echo $msg . "\n";
    // Versão web do servidor (web/): a página responde as perguntas, não tem terminal
    if (function_exists('replicar_web_resposta')) {
        return replicar_web_resposta($msg);
    }
    ob_flush();
    $in = trim(fgets(STDIN));
    return $in;
}

$hostname = gethostname();
$usuario_sistema = get_current_user();

$servidor_ricardo = 0;
$win = 0;
$jundiai = 0;
$linux = 0;

// 1. Verifica se está no servidor do Ricardo
if ($usuario_sistema === 'ricardo' || is_dir("/home/ricardo/web/dev.aguiarsoftware.com.br/public_html/replicar")) {
    $servidor_ricardo = 1;
    $plat = "/home/ricardo/web/dev.aguiarsoftware.com.br/public_html";
    echo "Executando no Servidor do Ricardo\n";

// 2. Verifica se está no Windows
} elseif (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $win = 1;
    $plat = "D:";
    $diretorio = "D:/Daniel Alves/Trabalho/Daniel Alves/replicar";

    if (is_dir($diretorio)) {
        echo "Você está em Jundiaí\n";
        $jundiai = 1;
        $plat = "D:/Daniel Alves/Trabalho/Daniel Alves";
    } else {
        echo "Você não está em Jundiaí - Diretório não encontrado\n";
    }

// 3. Caso contrário, assume Linux local
} else {
    $linux = 1;
    $plat = "/media/daniel_alves/Novo volume";
    echo "Executando no Linux Local\n";
}

// Carregamento da classe de acordo com o ambiente identificado
if ($servidor_ricardo) {
    require "/home/ricardo/web/dev.aguiarsoftware.com.br/public_html/replicar/replicar_class.php";
    // $plat aqui é a base dos backups locais (Sacos, Comary, IFEN, CEERJ, Aequor, Sistema...).
    // Aponta pra pasta pessoal no HD (mesma usada no Linux local), e não pro public_html do site,
    // já que public_html não tem essas pastas de backup.
    $plat = "/media/daniel_alves/Novo volume/Clientes/Aguiarsoft Ricardo";
} elseif ($jundiai) {
    require $plat . "/replicar/replicar_class.php";
    $plat = "D:/Daniel Alves/Trabalho";
} else {
    require $plat . "/Clientes/Daniel Alves/Replicar/replicar_class.php";
    $plat = $plat . "/Clientes/Aguiarsoft Ricardo";
}

// $mydir precisa permanecer com o caminho ABSOLUTO completo, pois é usado em
// file_exists(), copiarArquivo(), enviarArquivoAoFTP() etc. mais abaixo no script
$mydir = str_replace(DIRECTORY_SEPARATOR, "/", getcwd());

// $mydir_relativo é só para exibição no console - não interfere na lógica do script
$mydir_relativo = $mydir;
$marcador = "administrator/components/";
$pos = strpos($mydir_relativo, $marcador);
if ($pos !== false) {
    $mydir_relativo = substr($mydir_relativo, $pos + strlen($marcador));
}
echo $mydir_relativo . "\n";

// Extrai o caminho relativo do componente (ex: com_asfinancas/models) a partir do $mydir absoluto.
// Localmente a estrutura de pastas segue .../<ano>/<pasta do componente>.
// No servidor do Ricardo não existe pasta de ano - a estrutura vai direto em .../administrator/components/<pasta do componente>.
function extrairDirRelativo($mydir, $ano, $servidor_ricardo)
{
    if ($servidor_ricardo) {
        $marcador = "administrator/components/";
        $pos = strpos($mydir, $marcador);
        if ($pos !== false) {
            return substr($mydir, $pos + strlen($marcador));
        }
        return $mydir;
    }

    $pos = strpos($mydir, $ano . "/");
    if ($pos !== false) {
        return substr($mydir, $pos + strlen($ano . "/"));
    }
    return $mydir;
}

// Dados de conexão SSH pro PC do Daniel (Tailscale) - usados apenas quando $servidor_ricardo,
// já que nesse caso o script roda fisicamente no servidor do Ricardo e não enxerga o HD local.
// A chave foi gerada com: ssh-keygen -t ed25519 -f ~/.ssh/replicar_key -N ""
$meu_pc_host = "100.65.35.63";
$meu_pc_user = "daniel_alves";
$meu_pc_ssh_key = (getenv('HOME') ?: '/root') . '/.ssh/replicar_key';

// Wrapper de copiarArquivo(): quando rodando no servidor do Ricardo (via SSH remoto), as pastas de
// backup local (Sacos Bay Plastic, Comary, IFEN, CEERJ, Aequor, Sistema...) não existem localmente,
// pois ficam no HD do Daniel - nesse caso envia o arquivo via SFTP (chave SSH) pro PC do Daniel,
// usando o mesmo caminho de destino ($destino) que já é montado em cima de $plat.
function copiarArquivoLocal($origem, $destino, $nome_arquivo, $servidor_ricardo)
{
    global $meu_pc_host, $meu_pc_user, $meu_pc_ssh_key, $mydir;

    if ($servidor_ricardo) {
        $pasta_local = dirname($origem);
        $pasta_destino = rtrim($destino, '/');
        replicar::enviarArquivoAoSFTP($meu_pc_host, $meu_pc_user, null, $pasta_local, $pasta_destino, $nome_arquivo, null, $mydir, $meu_pc_ssh_key);
        return;
    }
    replicar::copiarArquivo($origem, $destino, $nome_arquivo);
}

// Mesma lógica do copiarArquivoLocal(), mas para cópia de pasta inteira
function copiarDiretorioLocal($origem, $destino, $nome_pasta, $flag, $servidor_ricardo)
{
    global $meu_pc_host, $meu_pc_user, $meu_pc_ssh_key, $mydir;

    if ($servidor_ricardo) {
        $pasta_destino = rtrim($destino, '/');
        replicar::enviarPastaAoSFTP($meu_pc_host, $meu_pc_user, null, $origem, $pasta_destino, $mydir, $meu_pc_ssh_key);
        return;
    }
    replicar::copiarDiretorio($origem, $destino, $nome_pasta, $flag);
}

// Lista de sites: vem do sites.json (mesma pasta deste arquivo) - é a mesma lista usada pela versão web
// e pelo replicar_bd.php. Pode ser editada na tela "Sites" da versão web ou direto no arquivo.
// Aqui só entram os sites ativos que têm a parte "arquivos" (envio por FTP/SFTP).
// No servidor do Ricardo ele fica em ~/.replicar/sites.json, fora do public_html: lá os arquivos
// da pasta replicar podem ser baixados pelo navegador, e o sites.json tem as senhas.
// Aqui no servidor a lista fica em .sites.json (nome com ponto: o site não deixa baixar pela web)
$arquivo_sites = __DIR__ . "/sites.json";
if (!file_exists($arquivo_sites)) {
    $arquivo_sites = __DIR__ . "/.sites.json";
}
if (!file_exists($arquivo_sites) && getenv("HOME")) {
    $arquivo_sites = getenv("HOME") . "/.replicar/sites.json";
}
$config_sites = json_decode((string) @file_get_contents($arquivo_sites), true);
if (!is_array($config_sites) || !isset($config_sites["sites"]) || !is_array($config_sites["sites"])) {
    echo "Não foi possível ler a lista de sites em " . $arquivo_sites . "\n";
    exit();
}
$sites = array();
foreach ($config_sites["sites"] as $s) {
    if (!empty($s["ativo"]) && !empty($s["arquivos"])) {
        $sites[] = array_merge($s["arquivos"], array("id" => $s["id"], "nome" => $s["nome"]));
    }
}

$excessao = array(
    "custom.css",
    "/models/nfe.php"
);

$excessao_exclusiva = array();
$excessao_exclusiva["vendas"]["comary"] = array(
    "/order/tmpl/userinfo.php",
    "/order/tmpl/default.php",
    "/clientes/tmpl/default.php",
    "/clientes/tmpl/edit.php",
    "/funcionarios/tmpl/editvendedor.php",
    "/funcionarios/tmpl/default.php",
    "/funcionarios/tmpl/edit.php",
    "/funcionarios/tmpl/editvendedor.php",
);
$excessao_exclusiva["compras"]["comary"] = array(
    "/order/tmpl/default.php",
    "/clientes/tmpl/default.php",
    "/clientes/tmpl/edit.php",
    "/funcionarios/tmpl/editvendedor.php",
    "/funcionarios/tmpl/default.php",
    "/funcionarios/tmpl/edit.php",
    "/funcionarios/tmpl/editvendedor.php",
);
$excessao_exclusiva["vendas"]["ceerj"] = array(
    "/order/tmpl/default.php",
    "/clientes/tmpl/default.php",
    "/clientes/tmpl/edit.php",
    "/funcionarios/tmpl/editvendedor.php",
    "/funcionarios/tmpl/default.php",
    "/funcionarios/tmpl/edit.php",
    "/funcionarios/tmpl/editvendedor.php",
);
$excessao_exclusiva["compras"]["ceerj"] = array(
    "/order/tmpl/default.php",
    "/clientes/tmpl/default.php",
    "/clientes/tmpl/edit.php",
    "/funcionarios/tmpl/editvendedor.php",
    "/funcionarios/tmpl/default.php",
    "/funcionarios/tmpl/edit.php",
    "/funcionarios/tmpl/editvendedor.php",
);

$excessao_alguns = array(
    "/asrelatorios/tmpl/menu.php",
    "/com_virtuemart/tables/products.php",
);

$jacopiei = 0;
$pausa = "n";
$tipo = "";
$solucao = "";
$ano = date("Y");

$nome_arquivo_modificado = replicar::pegarUltimoArquivoModificado();
echo "Último arquivo alterado encontrado = " . ($nome_arquivo_modificado ?: '[Nenhum encontrado]') . "\n";

$nome_arquivo = prompt("Deseja escrever o nome do arquivo a ser usado? (Enter para nao escrever, escreva 'pasta=nome da pasta' para o upload da pasta toda!)");
if (!$nome_arquivo) {
    $nome_arquivo = $nome_arquivo_modificado;
}

if (!$nome_arquivo) {
    echo "Não é possível replicar. Nenhum arquivo foi selecionado ou encontrado.\n";
    exit();
}

if (!file_exists($mydir . "/" . $nome_arquivo) && strpos($nome_arquivo, "pasta") === false && strpos($nome_arquivo, "Pasta") === false) {
    echo 'Não é possível replicar. Arquivo "' . $nome_arquivo . '" não existe em: ' . $mydir . "\n";
    exit();
}

$dir = extrairDirRelativo($mydir, $ano, $servidor_ricardo);
$dir_meio = str_replace($ano . "/" . $dir, "", $mydir);

// Compatível com PHP 8+: substituição do end() e prev()
$dir_meio_arr = array_values(array_filter(explode("/", $dir_meio)));
$count_meio = count($dir_meio_arr);
$dir_meio = ($count_meio >= 2) ? $dir_meio_arr[$count_meio - 2] : '';

$pastas_ftp_meio = "administrator/components/";
if (strpos($dir, "invoice") !== false) {
    $tipo = "vendas antigo";
}
if (strpos($dir, "invoice3") !== false) {
    $tipo = "vendas";
}
if (strpos($dir, "compra") !== false) {
    $tipo = "compras";
}
if (strpos($dir, "servicos") !== false) {
    $tipo = "servicos";
}
if (strpos($dir, "adm/com_virtuemart") !== false) {
    $tipo = "virtuemart";
    $retirar = substr($dir . 'com_virtuemart', 0, strpos($dir, 'com_virtuemart'));
    $dir = str_replace($retirar, "", $dir);
    $ano = "Modificações/adm";
}
if (strpos($dir, "com_virtuemart") !== false) {
    $tipo = "virtuemart";
    $retirar = substr($dir . 'com_virtuemart', 0, strpos($dir, 'com_virtuemart'));
    $dir = str_replace($retirar, "", $dir);
}
if (strpos($dir, "virtuemart_front") !== false) {
    $pastas_ftp_meio = "components/";
    $tipo = "virtuemart_front";
    $pos = strpos($dir, 'virtuemart_front');
    $resto = substr($dir, $pos + strlen('virtuemart_front'));
    $dir = 'com_virtuemart' . $resto;
}
if (strpos($dir, "Site Completo") !== false) {
    $tipo = "Site Completo";
    $retirar = substr($dir . 'Site Completo', 0, strpos($dir, 'Site Completo'));
    $dir = str_replace($retirar . "Site Completo/", "", $dir);
    $pastas_ftp_meio = "";
}
if (strpos($dir, "financas") !== false) {
    $tipo = "financas";
}
if (strpos($dir, "comissao") !== false) {
    $tipo = "comissao";
}
if (strpos($dir, "relatorios") !== false) {
    $tipo = "relatorios";
}

echo "Diretorio completo = " . $mydir . "\n";
echo "Diretorio do meio = " . $dir_meio . "\n";
echo "Pasta = " . $dir . "\n";
echo "Arquivo ou pasta a replicar = " . $nome_arquivo . "\n";
echo "Tipo = " . $tipo . "\n";

$pesq = $mydir . "/" . $nome_arquivo;
foreach ($excessao as $exc) {
    if (strpos($pesq, $exc) !== false) {
        echo "Arquivo na Excessão! Não é possível replicar o arquivo " . $pesq . "\n";
        exit();
    }
}

$cont = prompt("Verifique se está tudo ok - Pressione enter para continuar e s para saír");
if (strtolower($cont) == "s") {
    echo "ok, saindo...";
    exit();
}
echo "Continuando...\n";

function eh_pasta($nome_arquivo)
{
    return strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false;
}

function nome_da_pasta($nome_arquivo)
{
    return str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
}

function perguntar_sn($msg)
{
    $resp = "";
    while (strtolower($resp) != "s" && strtolower($resp) != "n") {
        $resp = prompt($msg);
    }
    return strtolower($resp) == "s";
}

// O arquivo está na lista de exceções pra esse site?
function site_na_excecao($site, $pesq, $tipo)
{
    global $excessao_alguns, $excessao_exclusiva;

    if (!empty($site["ignorar_excecoes"])) {
        return false;
    }
    $bloqueado = false;
    foreach ($excessao_alguns as $exc) {
        if (strpos($pesq, $exc) !== false) {
            $bloqueado = true;
        }
    }
    $alias = isset($site["alias"]) ? $site["alias"] : "";
    if ($alias !== "" && !empty($excessao_exclusiva[$tipo][$alias])) {
        foreach ($excessao_exclusiva[$tipo][$alias] as $exc_exclusiva) {
            if (strpos($pesq, $exc_exclusiva) !== false) {
                $bloqueado = true;
            }
        }
    }
    if ($bloqueado) {
        echo "Arquivo na Excessão! Não é possível replicar o arquivo " . $pesq . " para o " . $site["nome"] . "\n";
    }
    return $bloqueado;
}

// Envia o arquivo (ou a pasta inteira, com "pasta=") pro site, por FTP ou SFTP
function enviar_ao_site($site, $pasta_remota_arquivo, $pasta_remota_pasta)
{
    global $mydir, $nome_arquivo;

    $sftp = isset($site["protocolo"]) && $site["protocolo"] === "sftp";
    if (eh_pasta($nome_arquivo)) {
        $nome_pasta = nome_da_pasta($nome_arquivo);
        echo "Enviando a pasta inteira " . $nome_pasta . " ao FTP...\n";
        if ($sftp) {
            replicar::enviarPastaAoSFTP($site["host"], $site["usuario"], $site["senha"], $mydir . "/" . $nome_pasta, $pasta_remota_pasta, $mydir);
        } else {
            replicar::enviarPastaAoFTP($site["host"], $site["usuario"], $site["senha"], $mydir . "/" . $nome_pasta, $pasta_remota_pasta, $mydir);
        }
    } else {
        if ($sftp) {
            replicar::enviarArquivoAoSFTP($site["host"], $site["usuario"], $site["senha"], $mydir, $pasta_remota_arquivo, $nome_arquivo, null, $mydir);
        } else {
            replicar::enviarArquivoAoFTP($site["host"], $site["usuario"], $site["senha"], $mydir, $pasta_remota_arquivo, $nome_arquivo, null, $mydir);
        }
    }
}

// Base das pastas de backup do grupo Sistema (as dos clientes usam $plat, definido lá em cima)
$plat_sistema = !empty($win) ? "D:" : "/media/daniel_alves/Novo volume";

// Caminhos dos sites com cópia local na pasta Sistema (Sistema, Solução Multi, NSMart, Dev Ricardo...).
// Partem do caminho relativo "cru" e tratam o virtuemart do mesmo jeito que o bloco do Sistema fazia.
$dir_sis = extrairDirRelativo($mydir, $ano, $servidor_ricardo);
$pastas_ftp_meio_sis = $pastas_ftp_meio;
if (strpos($dir_sis, "adm/com_virtuemart") !== false || strpos($dir_sis, "com_virtuemart") !== false) {
    $retirar = substr($dir_sis . 'com_virtuemart', 0, strpos($dir_sis, 'com_virtuemart'));
    $dir_sis = str_replace($retirar, "", $dir_sis);
}
if (strpos($dir_sis, "virtuemart_front") !== false) {
    $pastas_ftp_meio_sis = "components/";
    $pos = strpos($dir_sis, 'virtuemart_front');
    $resto = substr($dir_sis, $pos + strlen('virtuemart_front'));
    $dir_sis = 'com_virtuemart' . $resto;
}

// Onde fica o componente dentro da pasta Sistema do HD e como a pasta se chama lá
$caminho_sis = "";
$pasta_ftp_sis = $dir_sis;
$dir_backup_sis = $dir_sis;
if (strpos($mydir, 'nvoice') !== false) {
    $caminho_sis = "Vendas/VM Invoice 3";
    $dir_backup_sis = str_replace("com_vminvoice3", "Vm Invoice3", $dir_sis);
} else if (strpos($mydir, 'ompra') !== false) {
    $caminho_sis = "Compras/VM Compra 3";
    $dir_backup_sis = str_replace("com_vmcompra3", "Vm Compra3", $dir_sis);
} else if (strpos($mydir, 'inancas') !== false) {
    $caminho_sis = "AS Finanças/As Finanças 3";
    $dir_backup_sis = str_replace("com_asfinancas", "admin", $dir_sis);
} else if (strpos($mydir, 'comissao') !== false) {
    $caminho_sis = "AS Comissao/AS Comissao3/com_comissao";
    $dir_backup_sis = str_replace("com_comissao", "admin", $dir_sis);
} else if (strpos($mydir, 'relatorios') !== false) {
    $caminho_sis = "AS Relatorios/AS Relatorios 3/com_asrelatorios";
    $dir_backup_sis = str_replace("com_asrelatorios", "admin", $dir_sis);
} else if (strpos($mydir, 'servicos') !== false) {
    $caminho_sis = "VM Servicos/admin";
    $dir_backup_sis = str_replace("com_vmservicos/", "", $dir_sis);
} else if (strpos($mydir, 'virtuemart') !== false) {
    $caminho_sis = "Site com sistema para instalação/Site Completo/" . $pastas_ftp_meio_sis;
}

// Cópia local pra pasta do cliente (Sacos Bay Plastic, Clube Comary, IFEN...)
function copia_local_cliente($site)
{
    global $mydir, $nome_arquivo, $dir_meio, $plat, $ano, $dir, $servidor_ricardo;

    $pasta_cliente = $site["pasta_cliente"];
    if ($dir_meio != $pasta_cliente && file_exists($mydir . "/" . $nome_arquivo)) {
        copiarArquivoLocal($mydir . "/" . $nome_arquivo, $plat . "/" . $pasta_cliente . "/" . $ano . "/" . $dir . "/", $nome_arquivo, $servidor_ricardo);
    } elseif (eh_pasta($nome_arquivo)) {
        $nome_pasta = nome_da_pasta($nome_arquivo);
        $src = $mydir . "/" . $nome_pasta;
        $dst = $plat . "/" . $pasta_cliente . "/" . $ano . "/" . $dir . "/" . $nome_pasta;
        copiarDiretorioLocal($src, $dst, $nome_pasta, 1, $servidor_ricardo);
    } else {
        echo "O arquivo " . $nome_arquivo . " nao foi copiado pra " . $site["nome"] . ".\n";
    }
}

// Cópia local pra pasta Sistema - feita uma vez só, no primeiro site do grupo que rodar
// (igual antes: só no envio de arquivo, não no de pasta inteira)
function copia_local_sistema()
{
    global $mydir, $nome_arquivo, $plat_sistema, $caminho_sis, $dir_backup_sis, $jacopiei, $servidor_ricardo;

    if (!file_exists($mydir . "/" . $nome_arquivo)) {
        return;
    }
    if (strpos($mydir, 'Sistema') === false && $jacopiei == 0) {
        copiarArquivoLocal($mydir . "/" . $nome_arquivo, $plat_sistema . "/Sistema/" . $caminho_sis . "/" . $dir_backup_sis . "/", $nome_arquivo, $servidor_ricardo);
        $jacopiei = 1;
    } elseif ($jacopiei) {
        echo "O arquivo ou pasta " . $nome_arquivo . " já havia sido copiado para a pasta de sistema.\n";
    } else {
        echo "O arquivo " . $nome_arquivo . " nao foi copiado para a pasta de sistema.\n";
    }
}

// Sites só pra um tipo específico (ex: "Ecorio site antigo" só pra "vendas antigo"): quando o tipo
// bate, só esses rodam; nos outros casos eles ficam de fora
$sites_do_tipo = array();
foreach ($sites as $site) {
    if (!empty($site["somente_tipo"]) && $site["somente_tipo"] === $tipo) {
        $sites_do_tipo[] = $site;
    }
}
if ($sites_do_tipo) {
    $sites_a_rodar = $sites_do_tipo;
} else {
    $sites_a_rodar = array();
    foreach ($sites as $site) {
        if (empty($site["somente_tipo"])) {
            $sites_a_rodar[] = $site;
        }
    }
}

$respostas = array();
foreach ($sites_a_rodar as $site) {
    echo "\n\n" . $site["nome"] . "\n";

    // Pergunta, ou segue a resposta de outro site (ex: Solução Multi vai junto com o Sistema)
    if (!empty($site["perguntar"])) {
        $vai = perguntar_sn("Deseja atualizar " . $site["nome"] . "?");
    } else {
        $junto = !empty($site["junto_com"]) ? $site["junto_com"] : "sistema";
        $vai = !empty($respostas[$junto]);
    }
    $respostas[$site["id"]] = $vai;
    if (!$vai || site_na_excecao($site, $pesq, $tipo)) {
        continue;
    }

    $base = rtrim($site["pasta_remota"], "/");
    $copia = isset($site["copia_local"]) ? $site["copia_local"] : "nenhuma";

    if ($copia === "sistema") {
        copia_local_sistema();
        enviar_ao_site(
            $site,
            $base . "/" . $pastas_ftp_meio_sis . $pasta_ftp_sis,
            $base . "/" . $pastas_ftp_meio_sis . $dir_sis . "/" . nome_da_pasta($nome_arquivo)
        );
    } else {
        if ($copia === "cliente" && !empty($site["pasta_cliente"])) {
            copia_local_cliente($site);
        }
        enviar_ao_site(
            $site,
            $base . "/" . $pastas_ftp_meio . $dir,
            $base . "/" . $pastas_ftp_meio . $dir . "/" . nome_da_pasta($nome_arquivo)
        );
    }
}

if ($sites_do_tipo) {
    echo "Replicacao de " . $tipo . " terminada!";
    exit();
}

echo "Replicacao terminada!\n";
if (!empty($win)) {
    prompt("Pausa");
}
?>