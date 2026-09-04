<?php
error_reporting(E_ERROR | E_PARSE);

if (!defined('STDIN')) {
    define('STDIN', fopen('php://stdin', 'r'));
}

function prompt($msg)
{
    echo $msg . "\n";
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

$sites = array();

$sites[] = array(
    "Nome" => "Ecorio",
    "alias" => "ecorio",
    "ftp_host" => "vps4.nuneshost.com",
    "ftp_user_login" => "ecorioonline",
    "ftp_pass" => "3Caras&1Fera#",
    "ftp_pasta_do_site" => "site-novo-erro",
);

$sites[] = array(
    "Nome" => "Grupo Seal - Neto",
    "alias" => "gseal",
    "ftp_host" => "ftp.gruposeal.com.br",
    "ftp_user_login" => "gruposea",
    "ftp_pass" => "Se042022Ma072023",
    "ftp_pasta_do_site" => "site",
);

$sites[] = array(
    "Nome" => "Grupo New Smart",
    "alias" => "gnewsmart",
    "ftp_host" => "vps4.nuneshost.com",
    "ftp_user_login" => "gruponewsmart",
    "ftp_pass" => "3Caras&1Fera#",
    "ftp_pasta_do_site" => "site",
);

$sites[] = array(
    "Nome" => "Fibrolar",
    "alias" => "fibrolar",
    "ftp_host" => "vps4.nuneshost.com",
    "ftp_user_login" => "fibrolar",
    "ftp_pass" => "3Caras&1Fera#",
    "ftp_pasta_do_site" => "site",
);

$sites[] = array(
    "Nome" => "Limptek",
    "alias" => "limptek",
    "ftp_host" => "vps4.nuneshost.com",
    "ftp_user_login" => "pastalimptek",
    "ftp_pass" => "3Caras&1Fera#",
    "ftp_pasta_do_site" => "site",
);

$sites[] = array(
    "Nome" => "Golf",
    "alias" => "golf",
    "ftp_host" => "100.96.10.71",
    "ftp_user_login" => "ricardo",
    "ftp_pass" => "2209",
    "ftp_pasta_do_site" => "golf",
    "desenv" => 1,
    "sftp" => 1,
);

$sites[] = array(
    "Nome" => "Golf Plesk",
    "alias" => "golf",
    "ftp_host" => "vps4.nuneshost.com",
    "ftp_user_login" => "teregolf",
    "ftp_pass" => "Que#de4senha26br",
    "ftp_pasta_do_site" => "site",
    "desenv" => 0,
    "sftp" => 0,
);

$sites[] = array(
    "Nome" => "Bay - Dev Ricardo",
    "alias" => "bay",
    "ftp_host" => "100.96.10.71",
    "ftp_user_login" => "ricardo",
    "ftp_pass" => "2209",
    "ftp_pasta_do_site" => "bay",
    "desenv" => 1,
    "sftp" => 1,
);

$sites[] = array(
    "Nome" => "Newsmart - Dev Ricardo",
    "alias" => "newsmart",
    "ftp_host" => "100.96.10.71",
    "ftp_user_login" => "ricardo",
    "ftp_pass" => "2209",
    "ftp_pasta_do_site" => "newsmart",
    "desenv" => 1,
    "sftp" => 1,
);

$sites[] = array(
    "Nome" => "Casa de Portugal - Dev Ricardo",
    "alias" => "portugal",
    "ftp_host" => "100.96.10.71",
    "ftp_user_login" => "ricardo",
    "ftp_pass" => "2209",
    "ftp_pasta_do_site" => "portugal",
    "desenv" => 1,
    "sftp" => 1,
);

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

if ($tipo == "vendas antigo") {
    $ecorio_resp = "";
    while (strtolower($ecorio_resp) != "s" && strtolower($ecorio_resp) != "n") {
        $ecorio_resp = prompt("Deseja atualizar a Ecorio site antigo?");
    }
    if (strtolower($ecorio_resp) == "s") {
        if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false) {
            $nome_pasta = str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
            $pasta_ftp = "/httpdocs/site/" . $pastas_ftp_meio . $dir . "/" . $nome_pasta;
            echo "Enviando a pasta inteira " . $nome_pasta . " ao FTP...\n";
            replicar::enviarPastaAoFTP("vps4.nuneshost.com", "ecorioonline", "3Caras&1Fera#", $mydir . "/" . $nome_pasta, $pasta_ftp, $mydir);
            while (strtolower($ecorio_resp2) != "s" && strtolower($ecorio_resp) != "n") {
                $ecorio_resp2 = prompt("Deseja atualizar a Ecorio site antigo no Dev Ricardo?");
                if (strtolower($ecorio_resp) == "s") {

                } else {
                    echo "Ok pulando Ecorio site antigo no Dev Ricardo";
                }
            }
        } else {
            replicar::enviarArquivoAoFTP("vps4.nuneshost.com", "ecorioonline", "3Caras&1Fera#", $mydir, "/httpdocs/site/" . $pastas_ftp_meio . $dir, $nome_arquivo, null, $mydir);
            while (strtolower($ecorio_resp2) != "s" && strtolower($ecorio_resp) != "n") {
               $ecorio_resp2 = prompt("Deseja atualizar a Ecorio site antigo no Dev Ricardo?");
               if (strtolower($ecorio_resp) == "s") {

                } else {
                    echo "Ok pulando Ecorio site antigo no Dev Ricardo";
                }
            }
        }
    }
    echo "Replicacao de vendas antigo terminada!";
    exit();
}

$sacos_teste = "";
while (strtolower($sacos_teste) != "s" && strtolower($sacos_teste) != "n") {
    $sacos_teste = prompt("Deseja atualizar a Sacos Teste?");
}
if ($excessao_alguns) {
    foreach ($excessao_alguns as $exc) {
        if (strpos($pesq, $exc) !== false) {
            echo "Arquivo na Excessão! Não é possível replicar o arquivo " . $pesq . " para a Sacos\n";
            $sacos_teste = "n";
        }
    }
}
if (!empty($excessao_exclusiva[$tipo]["sacos"])) {
    foreach ($excessao_exclusiva[$tipo]["sacos"] as $exc_exclusiva) {
        if (strpos($pesq, $exc_exclusiva) !== false) {
            echo "Arquivo na Excessão! Não é possível replicar o arquivo " . $pesq . " para a Sacos\n";
            $sacos_teste = "n";
        }
    }
}
if (strtolower($sacos_teste) == "s") {
    if ($dir_meio != "Sacos Bay Plastic" && file_exists($mydir . "/" . $nome_arquivo)) {
        copiarArquivoLocal($mydir . "/" . $nome_arquivo, $plat . "/Sacos Bay Plastic/" . $ano . "/" . $dir . "/", $nome_arquivo, $servidor_ricardo);
    } else {
        if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false) {
            $nome_pasta = str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
            $src = $mydir . "/" . $nome_pasta;
            $dst = $plat . "/Sacos Bay Plastic/" . $ano . "/" . $dir . "/" . $nome_pasta;
            copiarDiretorioLocal($src, $dst, $nome_pasta, 1, $servidor_ricardo);
        } else {
            echo "O arquivo " . $nome_arquivo . " nao foi copiado pra Sacos.\n";
        }
    }
    if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false) {
        $nome_pasta = str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
        $pasta_ftp = "/httpdocs/teste$$$/" . $pastas_ftp_meio . $dir . "/" . $nome_pasta;
        echo "Enviando a pasta inteira " . $nome_pasta . " ao FTP...\n";
        replicar::enviarPastaAoFTP("vps4.nuneshost.com", "sacosbayplastic", "3Caras&1Fera#", $mydir . "/" . $nome_pasta, $pasta_ftp, $mydir);
    } else {
        replicar::enviarArquivoAoFTP("vps4.nuneshost.com", "sacosbayplastic", "3Caras&1Fera#", $mydir, "/httpdocs/teste$$$/" . $pastas_ftp_meio . $dir, $nome_arquivo, null, $mydir);
    }
}

echo "\n\nSacos\n";
$sacos_site = "";
while (strtolower($sacos_site) != "s" && strtolower($sacos_site) != "n") {
    $sacos_site = prompt("Deseja atualizar a Sacos Site?");
}
if ($excessao_alguns) {
    foreach ($excessao_alguns as $exc) {
        if (strpos($pesq, $exc) !== false) {
            echo "Arquivo na Excessão! Não é possível replicar o arquivo " . $pesq . " para a Sacos\n";
            $sacos_site = "n";
        }
    }
}
if (!empty($excessao_exclusiva[$tipo]["sacos"])) {
    foreach ($excessao_exclusiva[$tipo]["sacos"] as $exc_exclusiva) {
        if (strpos($pesq, $exc_exclusiva) !== false) {
            echo "Arquivo na Excessão! Não é possível replicar o arquivo " . $pesq . " para a Sacos\n";
            $sacos_site = "n";
        }
    }
}
if (strtolower($sacos_site) == "s") {
    if ($dir_meio != "Sacos Bay Plastic" && file_exists($mydir . "/" . $nome_arquivo)) {
        copiarArquivoLocal($mydir . "/" . $nome_arquivo, $plat . "/Sacos Bay Plastic/" . $ano . "/" . $dir . "/", $nome_arquivo, $servidor_ricardo);
    } else {
        if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false) {
            $nome_pasta = str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
            $src = $mydir . "/" . $nome_pasta;
            $dst = $plat . "/Sacos Bay Plastic/" . $ano . "/" . $dir . "/" . $nome_pasta;
            copiarDiretorioLocal($src, $dst, $nome_pasta, 1, $servidor_ricardo);
        } else {
            echo "O arquivo " . $nome_arquivo . " nao foi copiado pra Sacos.\n";
        }
    }
    if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false) {
        $nome_pasta = str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
        $pasta_ftp = "/httpdocs/site/" . $pastas_ftp_meio . $dir . "/" . $nome_pasta;
        echo "Enviando a pasta inteira " . $nome_pasta . " ao FTP...\n";
        replicar::enviarPastaAoFTP("vps4.nuneshost.com", "sacosbayplastic", "3Caras&1Fera#", $mydir . "/" . $nome_pasta, $pasta_ftp, $mydir);
    } else {
        replicar::enviarArquivoAoFTP("vps4.nuneshost.com", "sacosbayplastic", "3Caras&1Fera#", $mydir, "/httpdocs/site/" . $pastas_ftp_meio . $dir, $nome_arquivo, null, $mydir);
    }
}

echo "\n\nComary\n";
$comary = "";
while (strtolower($comary) != "s" && strtolower($comary) != "n") {
    $comary = prompt("Deseja atualizar o Comary?");
}
if ($excessao_alguns) {
    foreach ($excessao_alguns as $exc) {
        if (strpos($pesq, $exc) !== false) {
            echo "Arquivo na Excessão! Não é possível replicar o arquivo " . $pesq . " para o Comary\n";
            $comary = "n";
        }
    }
}
if (!empty($excessao_exclusiva[$tipo]["comary"])) {
    foreach ($excessao_exclusiva[$tipo]["comary"] as $exc_exclusiva) {
        if (strpos($pesq, $exc_exclusiva) !== false) {
            echo "Arquivo na Excessão! Não é possível replicar o arquivo " . $pesq . " para o Comary\n";
            $comary = "n";
        }
    }
}
if (strtolower($comary) == "s") {
    if ($dir_meio != "Clube Comary" && file_exists($mydir . "/" . $nome_arquivo)) {
        copiarArquivoLocal($mydir . "/" . $nome_arquivo, $plat . "/Clube Comary/" . $ano . "/" . $dir . "/", $nome_arquivo, $servidor_ricardo);
    } else {
        if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false) {
            $nome_pasta = str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
            $src = $mydir . "/" . $nome_pasta;
            $dst = $plat . "/Clube Comary/" . $ano . "/" . $dir . "/" . $nome_pasta;
            copiarDiretorioLocal($src, $dst, $nome_pasta, 1, $servidor_ricardo);
        } else {
            echo "O arquivo " . $nome_arquivo . " nao foi copiado pro Comary.\n";
        }
    }
    if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false) {
        $nome_pasta = str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
        $pasta_ftp = "/httpdocs/site/" . $pastas_ftp_meio . $dir . "/" . $nome_pasta;
        echo "Enviando a pasta inteira " . $nome_pasta . " ao FTP...\n";
        replicar::enviarPastaAoFTP("vps4.nuneshost.com", "clubecomary", "3Caras&1Fera#", $mydir . "/" . $nome_pasta, $pasta_ftp, $mydir);
    } else {
        replicar::enviarArquivoAoFTP("vps4.nuneshost.com", "clubecomary", "3Caras&1Fera#", $mydir, "/httpdocs/site/" . $pastas_ftp_meio . $dir, $nome_arquivo, null, $mydir);
    }
}

echo "\n\nIfen\n";
$ifen = "";
while (strtolower($ifen) != "s" && strtolower($ifen) != "n") {
    $ifen = prompt("Deseja atualizar o Edições IFEN?");
}
if ($excessao_alguns) {
    foreach ($excessao_alguns as $exc) {
        if (strpos($pesq, $exc) !== false) {
            echo "Arquivo na Excessão! Não é possível replicar o arquivo " . $pesq . " para o Edições IFen\n";
            $ifen = "n";
        }
    }
}
if (!empty($excessao_exclusiva[$tipo]["ifen"])) {
    foreach ($excessao_exclusiva[$tipo]["ifen"] as $exc_exclusiva) {
        if (strpos($pesq, $exc_exclusiva) !== false) {
            echo "Arquivo na Excessão! Não é possível replicar o arquivo " . $pesq . " para o IFEN\n";
            $ifen = "n";
        }
    }
}
if (strtolower($ifen) == "s") {
    if ($dir_meio != "IFEN" && file_exists($mydir . "/" . $nome_arquivo)) {
        copiarArquivoLocal($mydir . "/" . $nome_arquivo, $plat . "/IFEN/" . $ano . "/" . $dir . "/", $nome_arquivo, $servidor_ricardo);
    } else {
        if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false) {
            $nome_pasta = str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
            $src = $mydir . "/" . $nome_pasta;
            $dst = $plat . "/IFEN/" . $ano . "/" . $dir . "/" . $nome_pasta;
            copiarDiretorioLocal($src, $dst, $nome_pasta, 1, $servidor_ricardo);
        } else {
            echo "O arquivo " . $nome_arquivo . " nao foi copiado pro IFEN.\n";
        }
    }
    if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false) {
        $nome_pasta = str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
        $pasta_ftp = "/httpdocs/" . $pastas_ftp_meio . $dir . "/" . $nome_pasta;
        echo "Enviando a pasta inteira " . $nome_pasta . " ao FTP...\n";
        replicar::enviarPastaAoFTP("vps4.nuneshost.com", "edicoesifen", "3Caras&1Fera#", $mydir . "/" . $nome_pasta, $pasta_ftp, $mydir);
    } else {
        replicar::enviarArquivoAoFTP("vps4.nuneshost.com", "edicoesifen", "3Caras&1Fera#", $mydir, "/httpdocs/" . $pastas_ftp_meio . $dir, $nome_arquivo, null, $mydir);
    }
}

echo "\n\nCEERJ\n";
$ceerj = "";
while (strtolower($ceerj) != "s" && strtolower($ceerj) != "n") {
    $ceerj = prompt("Deseja atualizar o CEERJ?");
}
if (!empty($excessao_exclusiva[$tipo]["ceerj"])) {
    foreach ($excessao_exclusiva[$tipo]["ceerj"] as $exc_exclusiva) {
        if (strpos($pesq, $exc_exclusiva) !== false) {
            echo "Arquivo na Excessão! Não é possível replicar o arquivo " . $pesq . " para o CEERJ\n";
            $ceerj = "n";
        }
    }
}
if ($excessao_alguns) {
    foreach ($excessao_alguns as $exc) {
        if (strpos($pesq, $exc) !== false) {
            echo "Arquivo na Excessão! Não é possível replicar o arquivo " . $pesq . " para o CEERJ\n";
            $ceerj = "n";
        }
    }
}
if (strtolower($ceerj) == "s") {
    if ($dir_meio != "CEERJ" && file_exists($mydir . "/" . $nome_arquivo)) {
        copiarArquivoLocal($mydir . "/" . $nome_arquivo, $plat . "/CEERJ/" . $ano . "/" . $dir . "/", $nome_arquivo, $servidor_ricardo);
    } else {
        if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false) {
            $nome_pasta = str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
            $src = $mydir . "/" . $nome_pasta;
            $dst = $plat . "/CEERJ/" . $ano . "/" . $dir . "/" . $nome_pasta;
            copiarDiretorioLocal($src, $dst, $nome_pasta, 1, $servidor_ricardo);
        } else {
            echo "O arquivo " . $nome_arquivo . " nao foi copiado pro CEERJ.\n";
        }
    }
    if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false) {
        $nome_pasta = str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
        $pasta_ftp = "/httpdocs/portal/" . $pastas_ftp_meio . $dir . "/" . $nome_pasta;
        echo "Enviando a pasta inteira " . $nome_pasta . " ao FTP...\n";
        replicar::enviarPastaAoFTP("vps4.nuneshost.com", "ceerj", "3Caras&1Fera#", $mydir . "/" . $nome_pasta, $pasta_ftp, $mydir);
    } else {
        replicar::enviarArquivoAoFTP("vps4.nuneshost.com", "ceerj", "3Caras&1Fera#", $mydir, "/httpdocs/portal/" . $pastas_ftp_meio . $dir, $nome_arquivo, null, $mydir);
    }
}

echo "\n\nAequor\n";
$aequor = "";
while (strtolower($aequor) != "s" && strtolower($aequor) != "n") {
    $aequor = prompt("Deseja atualizar o Aequor?");
}
if ($excessao_alguns) {
    foreach ($excessao_alguns as $exc) {
        if (strpos($pesq, $exc) !== false) {
            echo "Arquivo na Excessão! Não é possível replicar o arquivo " . $pesq . " para o Aequor\n";
            $aequor = "n";
        }
    }
}
if (!empty($excessao_exclusiva[$tipo]["aequor"])) {
    foreach ($excessao_exclusiva[$tipo]["aequor"] as $exc_exclusiva) {
        if (strpos($pesq, $exc_exclusiva) !== false) {
            echo "Arquivo na Excessão! Não é possível replicar o arquivo " . $pesq . " para a Aequor\n";
            $aequor = "n";
        }
    }
}
if (strtolower($aequor) == "s") {
    if ($dir_meio != "Aequor" && file_exists($mydir . "/" . $nome_arquivo)) {
        copiarArquivoLocal($mydir . "/" . $nome_arquivo, $plat . "/Aequor/" . $ano . "/" . $dir . "/", $nome_arquivo, $servidor_ricardo);
    } else {
        if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false) {
            $nome_pasta = str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
            $src = $mydir . "/" . $nome_pasta;
            $dst = $plat . "/Aequor/" . $ano . "/" . $dir . "/" . $nome_pasta;
            copiarDiretorioLocal($src, $dst, $nome_pasta, 1, $servidor_ricardo);
        } else {
            echo "O arquivo " . $nome_arquivo . " nao foi copiado pra Aequor.\n";
        }
    }
    if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false) {
        $nome_pasta = str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
        $pasta_ftp = "/httpdocs/site/" . $pastas_ftp_meio . $dir . "/" . $nome_pasta;
        echo "Enviando a pasta inteira " . $nome_pasta . " ao FTP...\n";
        replicar::enviarPastaAoFTP("vps4.nuneshost.com", "aequor", "3Caras&1Fera#", $mydir . "/" . $nome_pasta, $pasta_ftp, $mydir);
    } else {
        replicar::enviarArquivoAoFTP("vps4.nuneshost.com", "aequor", "3Caras&1Fera#", $mydir, "/httpdocs/site/" . $pastas_ftp_meio . $dir, $nome_arquivo, null, $mydir);
    }
}

echo "\n\nSistema\n";
$sistema = "";
while (strtolower($sistema) != "s" && strtolower($sistema) != "n") {
    $sistema = prompt("Deseja atualizar o Sistema?");
}

$dir = extrairDirRelativo($mydir, $ano, $servidor_ricardo);

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

if (strtolower($sistema) == "s") {
    if (file_exists($mydir . "/" . $nome_arquivo)) {
        if (strpos($mydir, 'nvoice') !== false) {
            $tipo = "vendas";
            $caminho = "Vendas/VM Invoice 3";
            $pasta_ftp = $dir;
            $dir = str_replace("com_vminvoice3", "Vm Invoice3", $dir);
        } else if (strpos($mydir, 'ompra') !== false) {
            $tipo = "compras";
            $caminho = "Compras/VM Compra 3";
            $pasta_ftp = $dir;
            $dir = str_replace("com_vmcompra3", "Vm Compra3", $dir);
        } else if (strpos($mydir, 'inancas') !== false) {
            $tipo = "financas";
            $caminho = "AS Finanças/As Finanças 3";
            $pasta_ftp = $dir;
            $dir = str_replace("com_asfinancas", "admin", $dir);
        } else if (strpos($mydir, 'comissao') !== false) {
            $tipo = "comissao";
            $caminho = "AS Comissao/AS Comissao3/com_comissao";
            $pasta_ftp = $dir;
            $dir = str_replace("com_comissao", "admin", $dir);
        } else if (strpos($mydir, 'relatorios') !== false) {
            $tipo = "relatorios";
            $caminho = "AS Relatorios/AS Relatorios 3/com_asrelatorios";
            $pasta_ftp = $dir;
            $dir = str_replace("com_asrelatorios", "admin", $dir);
        } else if (strpos($mydir, 'servicos') !== false) {
            $tipo = "servicos";
            $caminho = "VM Servicos/admin";
            $pasta_ftp = $dir;
            $dir = str_replace("com_vmservicos/", "", $dir);
        } else if (strpos($mydir, 'virtuemart_front') !== false) {
            $tipo = "virtuemart_front";
            $caminho = "Site com sistema para instalação/Site Completo/" . $pastas_ftp_meio;
            $pasta_ftp = $dir;
        } else if (strpos($mydir, 'virtuemart') !== false) {
            $tipo = "virtuemart";
            $caminho = "Site com sistema para instalação/Site Completo/" . $pastas_ftp_meio;
            $pasta_ftp = $dir;
        }

        if (!empty($win)) {
            $plat = "D:";
        } else if (!empty($linux)) {
            $plat = "/media/daniel_alves/Novo volume";
        }

        if (strpos($mydir, 'Sistema') === false && $jacopiei == 0) {
            copiarArquivoLocal($mydir . "/" . $nome_arquivo, $plat . "/Sistema/" . $caminho . "/" . $dir . "/", $nome_arquivo, $servidor_ricardo);
            $jacopiei = 1;
        } else {
            if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false) {
                $nome_pasta = str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
                $src = $mydir . "/" . $nome_pasta;
                $dst = $plat . "/Sistema/" . $caminho . "/" . $dir . "/" . $nome_pasta;
                copiarDiretorioLocal($src, $dst, $nome_pasta, 1, $servidor_ricardo);
                $jacopiei = 1;
            } else {
                echo "O arquivo " . $nome_arquivo . " nao foi copiado para a pasta de sistema.\n";
            }
        }
    }
    if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false) {
        $nome_pasta = str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
        $pasta_ftp = "/httpdocs/sistema/" . $pastas_ftp_meio . $dir . "/" . $nome_pasta;
        echo "Enviando a pasta inteira " . $nome_pasta . " ao FTP...\n";
        replicar::enviarPastaAoFTP("vps4.nuneshost.com", "grupointernet", "3Caras&1Fera#", $mydir . "/" . $nome_pasta, $pasta_ftp, $mydir);
    } else {
        replicar::enviarArquivoAoFTP("vps4.nuneshost.com", "grupointernet", "3Caras&1Fera#", $mydir, "/httpdocs/sistema/" . $pastas_ftp_meio . $pasta_ftp, $nome_arquivo, null, $mydir);
    }
}

echo "\n\nSolução Multi\n";
if (!empty($excessao_exclusiva[$tipo]["solucao"])) {
    foreach ($excessao_exclusiva[$tipo]["solucao"] as $exc_exclusiva) {
        if (strpos($pesq, $exc_exclusiva) !== false) {
            echo "Arquivo na Excessão! Não é possível replicar o arquivo " . $pesq . " para o Solucao\n";
            $solucao = "n";
            $sistema = "n";
        }
    }
}

$dir = extrairDirRelativo($mydir, $ano, $servidor_ricardo);

if (strtolower($sistema) == "s") {
    if (file_exists($mydir . "/" . $nome_arquivo)) {
        if (strpos($mydir, 'vminvoice') !== false) {
            $tipo = "vendas";
            $caminho = "Vendas/VM Invoice 3";
            $pasta_ftp = $dir;
            $dir = str_replace("com_vminvoice3", "Vm Invoice3", $dir);
        } else if (strpos($mydir, 'ompra') !== false) {
            $tipo = "compras";
            $caminho = "Compras/VM Compra 3";
            $pasta_ftp = $dir;
            $dir = str_replace("com_vmcompra3", "Vm Compra3", $dir);
        } else if (strpos($mydir, 'inancas') !== false) {
            $tipo = "financas";
            $caminho = "AS Finanças/As Finanças 3";
            $pasta_ftp = $dir;
            $dir = str_replace("com_asfinancas", "admin", $dir);
        } else if (strpos($mydir, 'comissao') !== false) {
            $tipo = "comissao";
            $caminho = "AS Comissao/AS Comissao3/com_comissao";
            $pasta_ftp = $dir;
            $dir = str_replace("com_comissao", "admin", $dir);
        } else if (strpos($mydir, 'relatorios') !== false) {
            $tipo = "relatorios";
            $caminho = "AS Relatorios/AS Relatorios 3/com_asrelatorios";
            $pasta_ftp = $dir;
            $dir = str_replace("com_asrelatorios", "admin", $dir);
        } else if (strpos($mydir, 'virtuemart_front') !== false) {
            $tipo = "virtuemart_front";
            $caminho = "Site com sistema para instalação/Site Completo/" . $pastas_ftp_meio;
            $pasta_ftp = str_replace("virtuemart_front", "com_virtuemart", $dir);
        } else if (strpos($mydir, 'virtuemart') !== false) {
            $tipo = "virtuemart";
            $caminho = "Site com sistema para instalação/Site Completo/" . $pastas_ftp_meio;
            $pasta_ftp = $dir;
        }

        if (strpos($mydir, 'Sistema') === false && $jacopiei == 0) {
            copiarArquivoLocal($mydir . "/" . $nome_arquivo, $plat . "/Sistema/" . $caminho . "/" . $dir . "/", $nome_arquivo, $servidor_ricardo);
            $jacopiei = 1;
        } else {
            if ($jacopiei) {
                echo "O arquivo ou pasta " . $nome_arquivo . " já havia sido copiado para a pasta de sistema.\n";
            } else {
                if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false) {
                    $nome_pasta = str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
                    $src = $mydir . "/" . $nome_pasta;
                    $dst = $plat . "/Sistema/" . $caminho . "/" . $dir . "/" . $nome_pasta;
                    copiarDiretorioLocal($src, $dst, $nome_pasta, 1, $servidor_ricardo);
                    $jacopiei = 1;
                } else {
                    echo "O arquivo " . $nome_arquivo . " nao foi copiado para a pasta de sistema.\n";
                }
            }
        }
    }
    if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false) {
        $nome_pasta = str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
        $pasta_ftp = "/httpdocs/solucaom-fora/" . $pastas_ftp_meio . $dir . "/" . $nome_pasta;
        echo "Enviando a pasta inteira " . $nome_pasta . " ao FTP...\n";
        replicar::enviarPastaAoFTP("vps4.nuneshost.com", "grupointernet", "3Caras&1Fera#", $mydir . "/" . $nome_pasta, $pasta_ftp, $mydir);
    } else {
        replicar::enviarArquivoAoFTP("vps4.nuneshost.com", "grupointernet", "3Caras&1Fera#", $mydir, "/httpdocs/solucaom-fora/" . $pastas_ftp_meio . $pasta_ftp, $nome_arquivo, null, $mydir);
    }
}
if ($solucao == "n") {
    $sistema = "s";
}

echo "\n\nNSMart\n";
$nsmart = "";
if ($excessao_alguns) {
    foreach ($excessao_alguns as $exc) {
        if (strpos($pesq, $exc) !== false) {
            echo "Arquivo na Excessão! Não é possível replicar o arquivo " . $pesq . " para o NSMart\n";
            $sistema = "n";
            $nsmart = "n";
        }
    }
}
if (!empty($excessao_exclusiva[$tipo]["nsmart"])) {
    foreach ($excessao_exclusiva[$tipo]["nsmart"] as $exc_exclusiva) {
        if (strpos($pesq, $exc_exclusiva) !== false) {
            echo "Arquivo na Excessão! Não é possível replicar o arquivo " . $pesq . " para o NSmart\n";
            $sistema = "n";
            $nsmart = "n";
        }
    }
}
$dir = extrairDirRelativo($mydir, $ano, $servidor_ricardo);

if (strtolower($sistema) == "s") {
    if (file_exists($mydir . "/" . $nome_arquivo)) {
        if (strpos($mydir, 'vminvoice') !== false) {
            $tipo = "vendas";
            $caminho = "Vendas/VM Invoice 3";
            $pasta_ftp = $dir;
            $dir = str_replace("com_vminvoice3", "Vm Invoice3", $dir);
        } else if (strpos($mydir, 'ompra') !== false) {
            $tipo = "compras";
            $caminho = "Compras/VM Compra 3";
            $pasta_ftp = $dir;
            $dir = str_replace("com_vmcompra3", "Vm Compra3", $dir);
        } else if (strpos($mydir, 'inancas') !== false) {
            $tipo = "financas";
            $caminho = "AS Finanças/As Finanças 3";
            $pasta_ftp = $dir;
            $dir = str_replace("com_asfinancas", "admin", $dir);
        } else if (strpos($mydir, 'comissao') !== false) {
            $tipo = "comissao";
            $caminho = "AS Comissao/AS Comissao3/com_comissao";
            $pasta_ftp = $dir;
            $dir = str_replace("com_comissao", "admin", $dir);
        } else if (strpos($mydir, 'relatorios') !== false) {
            $tipo = "relatorios";
            $caminho = "AS Relatorios/AS Relatorios 3/com_asrelatorios";
            $pasta_ftp = $dir;
            $dir = str_replace("com_asrelatorios", "admin", $dir);
        } else if (strpos($mydir, 'servicos') !== false) {
            $tipo = "servicos";
            $caminho = "VM Servicos/admin";
            $pasta_ftp = $dir;
            $dir = str_replace("com_vmservicos/", "", $dir);
        } else if (strpos($mydir, 'virtuemart_front') !== false) {
            $tipo = "virtuemart_front";
            $caminho = "Site com sistema para instalação/Site Completo/" . $pastas_ftp_meio;
            $pasta_ftp = str_replace("virtuemart_front", "com_virtuemart", $dir);
        } else if (strpos($mydir, 'virtuemart') !== false) {
            $tipo = "virtuemart";
            $caminho = "Site com sistema para instalação/Site Completo/" . $pastas_ftp_meio;
            $pasta_ftp = $dir;
        }
        if (strpos($mydir, 'Sistema') === false && $jacopiei == 0) {
            copiarArquivoLocal($mydir . "/" . $nome_arquivo, $plat . "/Sistema/" . $caminho . "/" . $dir . "/", $nome_arquivo, $servidor_ricardo);
            $jacopiei = 1;
        } else {
            if ($jacopiei) {
                echo "O arquivo ou pasta " . $nome_arquivo . " já havia sido copiado para a pasta de sistema.\n";
            } else {
                if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false) {
                    $nome_pasta = str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
                    $src = $mydir . "/" . $nome_pasta;
                    $dst = $plat . "/Sistema/" . $caminho . "/" . $dir . "/" . $nome_pasta;
                    copiarDiretorioLocal($src, $dst, $nome_pasta, 1, $servidor_ricardo);
                    $jacopiei = 1;
                } else {
                    echo "O arquivo " . $nome_arquivo . " nao foi copiado para a pasta de sistema.\n";
                }
            }
        }
    }
    if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false) {
        $nome_pasta = str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
        $pasta_ftp = "/httpdocs/site/" . $pastas_ftp_meio . $dir . "/" . $nome_pasta;
        echo "Enviando a pasta inteira " . $nome_pasta . " ao FTP...\n";
        replicar::enviarPastaAoFTP("vps4.nuneshost.com", "nsmart", "3Caras&1Fera#", $mydir . "/" . $nome_pasta, $pasta_ftp, $mydir);
    } else {
        replicar::enviarArquivoAoFTP("vps4.nuneshost.com", "nsmart", "3Caras&1Fera#", $mydir, "/httpdocs/site/" . $pastas_ftp_meio . $pasta_ftp, $nome_arquivo, null, $mydir);
    }
}
if ($nsmart == "n") {
    $sistema = "s";
}

$sistema_temp = $sistema;
foreach ($sites as $site) {
    echo "\n\n" . $site["Nome"] . "\n";
    $sitevar = "";
    $sistema = $sistema_temp;

    if (!empty($site["desenv"])) {
        $sitevar = "";
        while (strtolower($sitevar) != "s" && strtolower($sitevar) != "n") {
            $sitevar = prompt("Deseja atualizar o " . $site["Nome"] . "?");
            $sistema = "s";
        }
        if (strtolower($sitevar) != "s") {
            continue;
        }
    }

    if ($excessao_alguns) {
        foreach ($excessao_alguns as $exc) {
            if (strpos($pesq, $exc) !== false) {
                echo "Arquivo na Excessão! Não é possível replicar o arquivo " . $pesq . " para o " . $site["Nome"] . "\n";
                $sistema = "n";
                $pausa = "s";
            }
        }
    }
    if (!empty($excessao_exclusiva[$tipo][$site["alias"]])) {
        foreach ($excessao_exclusiva[$tipo][$site["alias"]] as $exc_exclusiva) {
            if (strpos($pesq, $exc_exclusiva) !== false) {
                echo "Arquivo na Excessão! Não é possível replicar o arquivo " . $pesq . " para o " . $site["Nome"] . "\n";
                $sistema = "n";
                $pausa = "s";
            }
        }
    }

    $dir = extrairDirRelativo($mydir, $ano, $servidor_ricardo);

    if (strtolower($sistema) == "s") {
        if (file_exists($mydir . "/" . $nome_arquivo)) {
            if (strpos($mydir, 'vminvoice') !== false) {
                $tipo = "vendas";
                $caminho = "Vendas/VM Invoice 3";
                $pasta_ftp = $dir;
                $dir = str_replace("com_vminvoice3", "Vm Invoice3", $dir);
            } elseif (strpos($mydir, 'ompra') !== false) {
                $tipo = "compras";
                $caminho = "Compras/VM Compra 3";
                $pasta_ftp = $dir;
                $dir = str_replace("com_vmcompra3", "Vm Compra3", $dir);
            } elseif (strpos($mydir, 'inancas') !== false) {
                $tipo = "financas";
                $caminho = "AS Finanças/As Finanças 3";
                $pasta_ftp = $dir;
                $dir = str_replace("com_asfinancas", "admin", $dir);
            } elseif (strpos($mydir, 'comissao') !== false) {
                $tipo = "comissao";
                $caminho = "AS Comissao/AS Comissao3/com_comissao";
                $pasta_ftp = $dir;
                $dir = str_replace("com_comissao", "admin", $dir);
            } elseif (strpos($mydir, 'relatorios') !== false) {
                $tipo = "relatorios";
                $caminho = "AS Relatorios/AS Relatorios 3/com_asrelatorios";
                $pasta_ftp = $dir;
                $dir = str_replace("com_asrelatorios", "admin", $dir);
            } else if (strpos($mydir, 'servicos') !== false) {
                $tipo = "servicos";
                $caminho = "VM Servicos/admin";
                $pasta_ftp = $dir;
                $dir = str_replace("com_vmservicos/", "", $dir);
            } else if (strpos($mydir, 'virtuemart_front') !== false) {
                $tipo = "virtuemart_front";
                $caminho = "Site com sistema para instalação/Site Completo/" . $pastas_ftp_meio;
                $pasta_ftp = str_replace("virtuemart_front", "com_virtuemart", $dir);
            } else if (strpos($mydir, 'virtuemart') !== false) {
                $tipo = "virtuemart";
                $caminho = "Site com sistema para instalação/Site Completo/" . $pastas_ftp_meio;
                $pasta_ftp = $dir;
            }

            if (strpos($mydir, 'Sistema') === false && $jacopiei == 0) {
                copiarArquivoLocal($mydir . "/" . $nome_arquivo, $plat . "/Sistema/" . $caminho . "/" . $dir . "/", $nome_arquivo, $servidor_ricardo);
                $jacopiei = 1;
            } else {
                if ($jacopiei) {
                    echo "O arquivo ou pasta " . $nome_arquivo . " já havia sido copiado para a pasta de sistema.\n";
                } else {
                    if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false) {
                        $nome_pasta = str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
                        $src = $mydir . "/" . $nome_pasta;
                        $dst = $plat . "/Sistema/" . $caminho . "/" . $dir . "/" . $nome_pasta;
                        copiarDiretorioLocal($src, $dst, $nome_pasta, 1, $servidor_ricardo);
                        $jacopiei = 1;
                    } else {
                        echo "O arquivo " . $nome_arquivo . " nao foi copiado para a pasta de sistema.\n";
                    }
                }
            }
        }

        if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false) {
            $nome_pasta = str_replace(array("pasta=", "Pasta="), "", $nome_arquivo);
            if (!empty($site["desenv"])) {
                $pasta_inicial = "/home/ricardo/web/dev.aguiarsoftware.com.br/public_html";
            } else {
                $pasta_inicial = "/httpdocs";
            }
            $pasta_ftp = $pasta_inicial . "/" . $site["ftp_pasta_do_site"] . "/" . $pastas_ftp_meio . $dir . "/" . $nome_pasta;
            echo "Enviando a pasta inteira " . $nome_pasta . " ao FTP...\n";
            if (!empty($site["sftp"])) {
                replicar::enviarPastaAoSFTP($site["ftp_host"], $site["ftp_user_login"], $site["ftp_pass"], $mydir . "/" . $nome_pasta, $pasta_ftp, $mydir);
            } else {
                replicar::enviarPastaAoFTP($site["ftp_host"], $site["ftp_user_login"], $site["ftp_pass"], $mydir . "/" . $nome_pasta, $pasta_ftp, $mydir);
            }
        } else {
            if (!empty($site["desenv"])) {
                $pasta_inicial = "/home/ricardo/web/dev.aguiarsoftware.com.br/public_html";
            } else {
                $pasta_inicial = "/httpdocs";
            }
            if (!empty($site["sftp"])) {
                replicar::enviarArquivoAoSFTP($site["ftp_host"], $site["ftp_user_login"], $site["ftp_pass"], $mydir, $pasta_inicial . "/" . $site["ftp_pasta_do_site"] . "/" . $pastas_ftp_meio . $pasta_ftp, $nome_arquivo, null, $mydir);
            } else {
                replicar::enviarArquivoAoFTP($site["ftp_host"], $site["ftp_user_login"], $site["ftp_pass"], $mydir, $pasta_inicial . "/" . $site["ftp_pasta_do_site"] . "/" . $pastas_ftp_meio . $pasta_ftp, $nome_arquivo, null, $mydir);
            }
        }
    }
    if ($pausa == "s") {
        $pausa = "n";
        $sistema = "s";
    }
}

echo "Replicacao terminada!\n";
if (!empty($win)) {
    prompt("Pausa");
}
?>