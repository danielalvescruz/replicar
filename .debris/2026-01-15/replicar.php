<?php
error_reporting(E_ERROR | E_PARSE);
define( 'STDIN', fopen( 'php://stdin', 'r' ));
function prompt( $msg )
{
	echo $msg . "\n";
	ob_flush();
	$in = trim( fgets( STDIN ));
	return $in;
}

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
	$win = 1;
	$plat = "D:";
} else {
	$linux = 1;
	$plat = "/media/daniel_alves/Novo volume";
}

require "/media/daniel_alves/Novo volume/Clientes/Daniel Alves/Replicar/replicar_class.php";

$mydir = str_replace(DIRECTORY_SEPARATOR, "/", getcwd());
echo $mydir."\n";
//prompt( "Pausa");

$sites = array();

$sites[] = array(
	"Nome" => "Ecorio",
	"alias" => "ecorio",
	"ftp_host" => "vps4.nunesti.com",
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
	"ftp_host" => "vps4.nunesti.com",
	"ftp_user_login" => "gruponewsmart",
	"ftp_pass" => "3Caras&1Fera#",
	"ftp_pasta_do_site" => "site",
);

$sites[] = array(
	"Nome" => "Fibrolar",
	"alias" => "fibrolar",
	"ftp_host" => "vps4.nunesti.com",
	"ftp_user_login" => "fibrolar",
	"ftp_pass" => "3Caras&1Fera#",
	"ftp_pasta_do_site" => "site",
);

$excessao = array(
	"custom.css"
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


$excessao_alguns= array(
	"/asrelatorios/tmpl/menu.php",
	"/com_virtuemart/tables/products.php",
);

$jacopiei = 0;
$ano=date("Y");
$nome_arquivo_modificado = replicar::pegarUltimoArquivoModificado();
echo "Último arquivo alterado encontrado = ".$nome_arquivo_modificado."\n";
$nome_arquivo = prompt( "Deseja escrever o nome do arquivo a ser usado? (Enter para nao escrever, escreva 'pasta=nome da pasta' para o upload da pasta toda!)");
if (!$nome_arquivo){
	$nome_arquivo = $nome_arquivo_modificado;
}
if (!file_exists($mydir."/".$nome_arquivo) && strpos($nome_arquivo, "pasta") === false && strpos($nome_arquivo, "Pasta") === false){
	echo 'Não é possível replicar. Arquivo "'.$nome_arquivo.'" não existe. Por favor tente novamente.';
	exit();
}
$dir = strpos($mydir, $ano."/");
$dir = substr($mydir, $dir);
$dir = str_replace($ano."/", "", $dir);
$dir_meio = str_replace($ano."/".$dir,"",$mydir);
//echo "dir_meio = ".$dir_meio."\n";
$dir_meio = explode("/",$dir_meio);
/*
echo "<pre>";
print_r($dir_meio);
echo "</pre>";
*/
end($dir_meio);
$dir_meio = prev($dir_meio);
$pastas_ftp_meio = "administrator/components/";
if (strpos($dir, "invoice") == true) {
	$tipo = "vendas antigo";
}
if (strpos($dir, "invoice3") == true) {
	$tipo = "vendas";
}
if (strpos($dir, "compra") == true) {
	$tipo = "compras";
}
if (strpos($dir, "servicos") == true) {
	$tipo = "servicos";
}
if (strpos($dir, "adm/com_virtuemart") == true) {
	$tipo = "virtuemart";
	$retirar = substr($dir.'com_virtuemart', 0, strpos($dir, 'com_virtuemart'));
	$dir = str_replace($retirar,"",$dir);
	$ano="Modificações/adm";
}
if (strpos($dir, "Site Completo") == true) {
	$tipo = "Site Completo";
	$retirar = substr($dir.'Site Completo', 0, strpos($dir, 'Site Completo'));
	$dir = str_replace($retirar."Site Completo/","",$dir);
	$pastas_ftp_meio = "";	
}
if (strpos($dir, "financas") == true) {
	$tipo = "financas";
}
if (strpos($dir, "comissao") == true) {
	$tipo = "comissao";
}
if (strpos($dir, "relatorios") == true) {
	$tipo = "relatorios";
}
echo "Diretorio completo = ".$mydir."\n";
echo "Diretorio do meio = ".$dir_meio."\n";
echo "Pasta = ".$dir."\n";
echo "Arquivo ou pasta a replicar = ".$nome_arquivo."\n";
echo "Tipo = ".$tipo."\n";
$pesq = $mydir."/".$nome_arquivo;
foreach ($excessao as $exc){
	if (strpos($pesq, $exc) == true) {
		echo "Arquivo na Excessão! Não é possível replicar o arquivo ".$pesq."\n";
		exit();
	}
}
$cont = prompt("Verifique se está tudo ok - Pressione enter para continuar e s para saír");
if ($cont == "s"  || $cont == "S"){
	echo "ok, saindo...";
    exit();
}
echo "Continuando...\n";

if ($tipo == "vendas antigo"){
	$$ecorio = "";
	while($$ecorio != "s" && $$ecorio != "S" && $$ecorio != "n" && $$ecorio != "N"){
		$$ecorio = prompt("Deseja atualizar a Ecorio site antigo?");	
	}
	/*
	if ($excessao_alguns){
		foreach ($excessao_alguns as $exc){
			if (strpos($pesq, $exc) == true) {
				echo "Arquivo na Excessão! Não é possível replicar o arquivo ".$pesq." para a Sacos\n";
				$$ecorio = "n";
			}
		}
	}
	if ($excessao_exclusiva[$tipo]["ecorio_antigo"]) {
		foreach ($excessao_exclusiva[$tipo]["ecorio_antigo"] as $exc_exclusiva) {
			if (strpos($pesq, $exc_exclusiva) == true) {
				echo "Arquivo na Excessão! Não é possível replicar o arquivo ".$pesq." para a Sacos\n";
				$$ecorio = "n";
			}
		}
	}
	*/
	if ($$ecorio == "S" || $$ecorio == "s"){
		if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false){
			$nome_pasta = str_replace(array("pasta=","Pasta="),"",$nome_arquivo);
			$pasta_ftp = "/httpdocs/site/".$pastas_ftp_meio.$dir."/".$nome_pasta;
			echo "Enviando a pasta inteira ".$nome_pasta." ao FTP...\n";
			replicar::enviarPastaAoFTP("vps4.nunesti.com","ecorioonline","3Caras&1Fera#",$mydir."/".$nome_pasta, $pasta_ftp, $mydir);		
		} else {
			replicar::enviarArquivoAoFTP("vps4.nunesti.com","ecorioonline","3Caras&1Fera#",$mydir,"/httpdocs/site/".$pastas_ftp_meio.$dir, $nome_arquivo, null, $mydir);
		}	
	}
	echo "Replicacao de vendas antigo terminada!";
	exit();
}

$sacos_teste = "";
while($sacos_teste != "s" && $sacos_teste != "S" && $sacos_teste != "n" && $sacos_teste != "N"){
	$sacos_teste = prompt("Deseja atualizar a Sacos Teste?");	
}
if ($excessao_alguns){
	foreach ($excessao_alguns as $exc){
		if (strpos($pesq, $exc) == true) {
			echo "Arquivo na Excessão! Não é possível replicar o arquivo ".$pesq." para a Sacos\n";
			$sacos_teste = "n";
		}
	}
}
if ($excessao_exclusiva[$tipo]["sacos"]) {
    foreach ($excessao_exclusiva[$tipo]["sacos"] as $exc_exclusiva) {
		if (strpos($pesq, $exc_exclusiva) == true) {
			echo "Arquivo na Excessão! Não é possível replicar o arquivo ".$pesq." para a Sacos\n";
			$sacos_teste = "n";
		}
    }
}
if ($sacos_teste == "S" || $sacos_teste == "s"){
	if ($dir_meio != "Sacos Bay Plastic" && file_exists($mydir."/".$nome_arquivo)){
		replicar::copiarArquivo($mydir."/".$nome_arquivo,$plat."/Clientes/Aguiarsoft Ricardo/Sacos Bay Plastic/".$ano."/".$dir."/",$nome_arquivo);
	} else {
		if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false){
			$nome_pasta = str_replace(array("pasta=","Pasta="),"",$nome_arquivo);
			$src = $mydir."/".$nome_pasta;
			$dst = $plat."/Clientes/Aguiarsoft Ricardo/Sacos Bay Plastic/".$ano."/".$dir."/".$nome_pasta;
			replicar::copiarDiretorio($src, $dst, $nome_pasta, 1);			
		} else {
			echo "O arquivo ".$nome_arquivo." nao foi copiado pra Sacos.\n";
		}
	}
	if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false){
		$nome_pasta = str_replace(array("pasta=","Pasta="),"",$nome_arquivo);
		$pasta_ftp = "/httpdocs/teste$$$/".$pastas_ftp_meio.$dir."/".$nome_pasta;
		echo "Enviando a pasta inteira ".$nome_pasta." ao FTP...\n";
		replicar::enviarPastaAoFTP("vps4.nunesti.com","sacosbayplastic","3Caras&1Fera#",$mydir."/".$nome_pasta, $pasta_ftp, $mydir);		
	} else {
		replicar::enviarArquivoAoFTP("vps4.nunesti.com","sacosbayplastic","3Caras&1Fera#",$mydir,"/httpdocs/teste$$$/".$pastas_ftp_meio.$dir, $nome_arquivo, null, $mydir);
	}	
}

echo "\n\nSacos\n";
$sacos_site = "";
while($sacos_site != "s" && $sacos_site != "S" && $sacos_site != "n" && $sacos_site != "N"){
	$sacos_site = prompt("Deseja atualizar a Sacos Site?");	
}
if ($excessao_alguns){
	foreach ($excessao_alguns as $exc){
		if (strpos($pesq, $exc) == true) {
			echo "Arquivo na Excessão! Não é possível replicar o arquivo ".$pesq." para a Sacos\n";
			$sacos_site = "n";
		}
	}
}
if ($excessao_exclusiva[$tipo]["sacos"]) {
    foreach ($excessao_exclusiva[$tipo]["sacos"] as $exc_exclusiva) {
		if (strpos($pesq, $exc_exclusiva) == true) {
			echo "Arquivo na Excessão! Não é possível replicar o arquivo ".$pesq." para a Sacos\n";
			$sacos_site = "n";
		}
    }
}
if ($sacos_site == "S" || $sacos_site == "s"){
	if ($dir_meio != "Sacos Bay Plastic" && file_exists($mydir."/".$nome_arquivo)){
		replicar::copiarArquivo($mydir."/".$nome_arquivo,$plat."/Clientes/Aguiarsoft Ricardo/Sacos Bay Plastic/".$ano."/".$dir."/",$nome_arquivo);
	} else {
		if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false){
			$nome_pasta = str_replace(array("pasta=","Pasta="),"",$nome_arquivo);
			$src = $mydir."/".$nome_pasta;
			$dst = $plat."/Clientes/Aguiarsoft Ricardo/Sacos Bay Plastic/".$ano."/".$dir."/".$nome_pasta;
			replicar::copiarDiretorio($src, $dst, $nome_pasta, 1);			
		} else {
			echo "O arquivo ".$nome_arquivo." nao foi copiado pra Sacos.\n";
		}
	}
	if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false){
		$nome_pasta = str_replace(array("pasta=","Pasta="),"",$nome_arquivo);
		$pasta_ftp = "/httpdocs/site/".$pastas_ftp_meio.$dir."/".$nome_pasta;
		echo "Enviando a pasta inteira ".$nome_pasta." ao FTP...\n";
		replicar::enviarPastaAoFTP("vps4.nunesti.com","sacosbayplastic","3Caras&1Fera#",$mydir."/".$nome_pasta, $pasta_ftp, $mydir);				
	} else {
        replicar::enviarArquivoAoFTP("vps4.nunesti.com", "sacosbayplastic", "3Caras&1Fera#", $mydir, "/httpdocs/site/".$pastas_ftp_meio.$dir, $nome_arquivo, null, $mydir);
    }
}

echo "\n\nComary\n";
$comary = "";
while($comary != "s" && $comary != "S" && $comary != "n" && $comary != "N"){
	$comary = prompt("Deseja atualizar o Comary?");	
}
if ($excessao_alguns){
	foreach ($excessao_alguns as $exc){
		if (strpos($pesq, $exc) == true) {
			echo "Arquivo na Excessão! Não é possível replicar o arquivo ".$pesq." para o Comary\n";
			$comary = "n";
		}
	}
}
if ($excessao_exclusiva[$tipo]["comary"]) {
    foreach ($excessao_exclusiva[$tipo]["comary"] as $exc_exclusiva) {
		if (strpos($pesq, $exc_exclusiva) == true) {
			echo "Arquivo na Excessão! Não é possível replicar o arquivo ".$pesq." para o Comary\n";
			$comary = "n";
		}
    }
}
if ($comary == "S" || $comary == "s"){
	if ($dir_meio != "Clube Comary" && file_exists($mydir."/".$nome_arquivo)){
		replicar::copiarArquivo($mydir."/".$nome_arquivo,$plat."/Clientes/Aguiarsoft Ricardo/Clube Comary/".$ano."/".$dir."/",$nome_arquivo);
	} else {
		if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false){
			$nome_pasta = str_replace(array("pasta=","Pasta="),"",$nome_arquivo);
			$src = $mydir."/".$nome_pasta;
			$dst = $plat."/Clientes/Aguiarsoft Ricardo/Clube Comary/".$ano."/".$dir."/".$nome_pasta;
			replicar::copiarDiretorio($src, $dst, $nome_pasta, 1);			
		} else {
			echo "O arquivo ".$nome_arquivo." nao foi copiado pro Comary.\n";
		}
	}
	if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false){
		$nome_pasta = str_replace(array("pasta=","Pasta="),"",$nome_arquivo);
		$pasta_ftp = "/httpdocs/site/".$pastas_ftp_meio.$dir."/".$nome_pasta;
		echo "Enviando a pasta inteira ".$nome_pasta." ao FTP...\n";
		replicar::enviarPastaAoFTP("vps4.nunesti.com","clubecomary","3Caras&1Fera#",$mydir."/".$nome_pasta, $pasta_ftp, $mydir);		
	} else {
        replicar::enviarArquivoAoFTP("vps4.nunesti.com", "clubecomary", "3Caras&1Fera#", $mydir, "/httpdocs/site/".$pastas_ftp_meio.$dir, $nome_arquivo, null, $mydir);
    }
}

echo "\n\nIfen\n";
$ifen = "";
while($ifen != "s" && $ifen != "S" && $ifen != "n" && $ifen != "N"){
	$ifen = prompt("Deseja atualizar o Edições IFEN?");	
}
if ($excessao_alguns){
	foreach ($excessao_alguns as $exc){
		if (strpos($pesq, $exc) == true) {
			echo "Arquivo na Excessão! Não é possível replicar o arquivo ".$pesq." para o Edições IFen\n";
			$ifen = "n";
		}
	}
}
if ($excessao_exclusiva[$tipo]["ifen"]) {
    foreach ($excessao_exclusiva[$tipo]["ifen"] as $exc_exclusiva) {
		if (strpos($pesq, $exc_exclusiva) == true) {
			echo "Arquivo na Excessão! Não é possível replicar o arquivo ".$pesq." para o IFEN\n";
			$ifen = "n";
		}
    }
}
if ($ifen == "S" || $ifen == "s"){
	if ($dir_meio != "IFEN" && file_exists($mydir."/".$nome_arquivo)){
		replicar::copiarArquivo($mydir."/".$nome_arquivo,$plat."/Clientes/Aguiarsoft Ricardo/IFEN/".$ano."/".$dir."/",$nome_arquivo);
	} else {
		if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false){
			$nome_pasta = str_replace(array("pasta=","Pasta="),"",$nome_arquivo);
			$src = $mydir."/".$nome_pasta;
			$dst = $plat."/Clientes/Aguiarsoft Ricardo/IFEN/".$ano."/".$dir."/".$nome_pasta;
			replicar::copiarDiretorio($src, $dst, $nome_pasta, 1);			
		} else {
			echo "O arquivo ".$nome_arquivo." nao foi copiado pro IFEN.\n";
		}
	}
	if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false){
		$nome_pasta = str_replace(array("pasta=","Pasta="),"",$nome_arquivo);
		$pasta_ftp = "/httpdocs/".$pastas_ftp_meio.$dir."/".$nome_pasta;
		echo "Enviando a pasta inteira ".$nome_pasta." ao FTP...\n";
		replicar::enviarPastaAoFTP("vps4.nunesti.com","edicoesifen","3Caras&1Fera#",$mydir."/".$nome_pasta, $pasta_ftp, $mydir);		
	} else {
        replicar::enviarArquivoAoFTP("vps4.nunesti.com", "edicoesifen", "3Caras&1Fera#", $mydir, "/httpdocs/".$pastas_ftp_meio.$dir, $nome_arquivo, null, $mydir);
    }
}

echo "\n\nCEERJ\n";
$ceerj = "";
while($ceerj != "s" && $ceerj != "S" && $ceerj != "n" && $ceerj != "N"){
	$ceerj = prompt("Deseja atualizar o CEERJ?");	
}
if ($excessao_exclusiva[$tipo]["ceerj"]) {
    foreach ($excessao_exclusiva[$tipo]["ceerj"] as $exc_exclusiva) {
		if (strpos($pesq, $exc_exclusiva) == true) {
			echo "Arquivo na Excessão! Não é possível replicar o arquivo ".$pesq." para o CEERJ\n";
			$ceerj = "n";
		}
    }
}
if ($excessao_alguns){
	foreach ($excessao_alguns as $exc){
		if (strpos($pesq, $exc) == true) {
			echo "Arquivo na Excessão! Não é possível replicar o arquivo ".$pesq." para o CEERJ\n";
			$ceerj = "n";
		}
	}
}
if ($ceerj == "S" || $ceerj == "s"){
	if ($dir_meio != "CEERJ" && file_exists($mydir."/".$nome_arquivo)){
		replicar::copiarArquivo($mydir."/".$nome_arquivo,$plat."/Clientes/Aguiarsoft Ricardo/CEERJ/".$ano."/".$dir."/",$nome_arquivo);
	} else {
		if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false){
			$nome_pasta = str_replace(array("pasta=","Pasta="),"",$nome_arquivo);
			$src = $mydir."/".$nome_pasta;
			$dst = $plat."/Clientes/Aguiarsoft Ricardo/CEERJ/".$ano."/".$dir."/".$nome_pasta;
			replicar::copiarDiretorio($src, $dst, $nome_pasta, 1);			
		} else {
			echo "O arquivo ".$nome_arquivo." nao foi copiado pro CEERJ.\n";
		}
	}
	if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false){
		$nome_pasta = str_replace(array("pasta=","Pasta="),"",$nome_arquivo);
		$pasta_ftp = "/httpdocs/portal/".$pastas_ftp_meio.$dir."/".$nome_pasta;
		echo "Enviando a pasta inteira ".$nome_pasta." ao FTP...\n";
		replicar::enviarPastaAoFTP("vps4.nunesti.com","ceerj","3Caras&1Fera#",$mydir."/".$nome_pasta, $pasta_ftp, $mydir);		
	} else {
        replicar::enviarArquivoAoFTP("vps4.nunesti.com", "ceerj", "3Caras&1Fera#", $mydir, "/httpdocs/portal/".$pastas_ftp_meio.$dir, $nome_arquivo, null, $mydir);
    }
}

echo "\n\nAequor\n";
$aequor = "";
while($aequor != "s" && $aequor != "S" && $aequor != "n" && $aequor != "N"){
	$aequor = prompt("Deseja atualizar o Aequor?");	
}
if ($excessao_alguns){
	foreach ($excessao_alguns as $exc){
		if (strpos($pesq, $exc) == true) {
			echo "Arquivo na Excessão! Não é possível replicar o arquivo ".$pesq." para o Aequor\n";
			$aequor = "n";
		}
	}
}
if ($excessao_exclusiva[$tipo]["aequor"]) {
    foreach ($excessao_exclusiva[$tipo]["aequor"] as $exc_exclusiva) {
		if (strpos($pesq, $exc_exclusiva) == true) {
			echo "Arquivo na Excessão! Não é possível replicar o arquivo ".$pesq." para a Aequor\n";
			$aequor = "n";
		}
    }
}
if ($aequor == "S" || $aequor == "s"){
	if ($dir_meio != "Aequor" && file_exists($mydir."/".$nome_arquivo)){
		replicar::copiarArquivo($mydir."/".$nome_arquivo,$plat."/Clientes/Aguiarsoft Ricardo/Aequor/".$ano."/".$dir."/",$nome_arquivo);
	} else {
		if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false){
			$nome_pasta = str_replace(array("pasta=","Pasta="),"",$nome_arquivo);
			$src = $mydir."/".$nome_pasta;
			$dst = $plat."/Clientes/Aguiarsoft Ricardo/Aequor/".$ano."/".$dir."/".$nome_pasta;
			replicar::copiarDiretorio($src, $dst, $nome_pasta, 1);			
		} else {
			echo "O arquivo ".$nome_arquivo." nao foi copiado pra Aequor.\n";
		}
	}
	if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false){
		$nome_pasta = str_replace(array("pasta=","Pasta="),"",$nome_arquivo);
		$pasta_ftp = "/httpdocs/site/".$pastas_ftp_meio.$dir."/".$nome_pasta;
		echo "Enviando a pasta inteira ".$nome_pasta." ao FTP...\n";
		replicar::enviarPastaAoFTP("vps4.nunesti.com","aequor","3Caras&1Fera#",$mydir."/".$nome_pasta, $pasta_ftp, $mydir);		
	} else {
        replicar::enviarArquivoAoFTP("vps4.nunesti.com", "aequor", "3Caras&1Fera#", $mydir, "/httpdocs/site/".$pastas_ftp_meio.$dir, $nome_arquivo, null, $mydir);
    }
}

echo "\n\nSistema\n";
$sistema = "";
while($sistema != "s" && $sistema != "S" && $sistema != "n" && $sistema != "N"){
	$sistema = prompt("Deseja atualizar o Sistema?");	
}
$dir = strpos($mydir, $ano."/");
$dir = substr($mydir, $dir);
$dir = str_replace($ano."/", "", $dir);
if (strpos($dir, "adm/com_virtuemart") == true) {
	$tipo = "virtuemart";
	$retirar = substr($dir.'com_virtuemart', 0, strpos($dir, 'com_virtuemart'));
	$dir = str_replace($retirar,"",$dir);
	$ano="Modificações/adm";
}
if ($sistema == "S" || $sistema == "s"){
	if (file_exists($mydir."/".$nome_arquivo)){
		if (strpos($mydir, 'nvoice') !== false) {
			$tipo = "vendas";
			$caminho = "Vendas/VM Invoice 3";
			$pasta_ftp = $dir;
			$dir = str_replace("com_vminvoice3","Vm Invoice3",$dir);
		} else if (strpos($mydir, 'ompra') !== false) {
			$tipo = "compras";
			$caminho = "Compras/VM Compra 3";
			$pasta_ftp = $dir;
			$dir = str_replace("com_vmcompra3","Vm Compra3",$dir);
		} else if (strpos($mydir, 'inancas') !== false) {
			$tipo = "financas";
			$caminho = "AS Finanças/As Finanças 3";
			$pasta_ftp = $dir;
			$dir = str_replace("com_asfinancas","admin",$dir);
		} else if (strpos($mydir, 'comissao') !== false) {
			$tipo = "comissao";
			$caminho = "AS Comissao/AS Comissao3/com_comissao";
			$pasta_ftp = $dir;
			$dir = str_replace("com_comissao","admin",$dir);
		} else if (strpos($mydir, 'relatorios') !== false) {
			$tipo = "relatorios";
			$caminho = "AS Relatorios/AS Relatorios 3/com_asrelatorios";
			$pasta_ftp = $dir;
			$dir = str_replace("com_asrelatorios","admin",$dir);
		} else if (strpos($mydir, 'servicos') !== false) {
			$tipo = "servicos";
			$caminho = "VM Servicos/admin";
			$pasta_ftp = $dir;
			$dir = str_replace("com_vmservicos/","",$dir);			
		} else if (strpos($mydir, 'virtuemart') !== false) {
			$tipo = "virtuemart";
			$caminho = "Site com sistema para instalação/Site Completo/".$pastas_ftp_meio;
			$pasta_ftp = $dir;
		}
		if (strpos($mydir, 'Sistema') === false && $jacopiei == 0){
			replicar::copiarArquivo($mydir."/".$nome_arquivo,$plat."/Sistema/".$caminho."/".$dir."/",$nome_arquivo);
			$jacopiei = 1;
		} else {
			if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false){
				$nome_pasta = str_replace(array("pasta=","Pasta="),"",$nome_arquivo);
				$src = $mydir."/".$nome_pasta;
				$dst = $plat."/Sistema/".$caminho."/".$dir."/".$nome_pasta;
				replicar::copiarDiretorio($src, $dst, $nome_pasta, 1);
				$jacopiei = 1;			
			} else {
				echo "O arquivo ".$nome_arquivo." nao foi copiado para a pasta de sistema.\n";
			}
		}
	} 
	if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false){
		$nome_pasta = str_replace(array("pasta=","Pasta="),"",$nome_arquivo);
		$pasta_ftp = "/httpdocs/sistema/".$pastas_ftp_meio.$dir."/".$nome_pasta;
		echo "Enviando a pasta inteira ".$nome_pasta." ao FTP...\n";
		replicar::enviarPastaAoFTP("vps4.nunesti.com","grupointernet","3Caras&1Fera#",$mydir."/".$nome_pasta, $pasta_ftp, $mydir);		
	} else {
		replicar::enviarArquivoAoFTP("vps4.nunesti.com", "grupointernet", "3Caras&1Fera#", $mydir, "/httpdocs/sistema/".$pastas_ftp_meio.$pasta_ftp, $nome_arquivo, null, $mydir);
    }
}

echo "\n\nSolução Multi\n";
$solucaom = "";
/*while($solucaom != "s" && $solucaom != "S" && $solucaom != "n" && $solucaom != "N"){
	$solucaom = prompt("Deseja atualizar o Solucaom?");	
}*/
if ($excessao_exclusiva[$tipo]["solucao"]) {
    foreach ($excessao_exclusiva[$tipo]["solucao"] as $exc_exclusiva) {
		if (strpos($pesq, $exc_exclusiva) == true) {
			echo "Arquivo na Excessão! Não é possível replicar o arquivo ".$pesq." para o Solucao\n";
			$solucao = "n";
			$sistema = "n";
		}
    }
}
$dir = strpos($mydir, $ano."/");
$dir = substr($mydir, $dir);
$dir = str_replace($ano."/", "", $dir);
//if ($solucaom == "S" || $solucaom == "s"){
if ($sistema == "S" || $sistema == "s"){
	if (file_exists($mydir."/".$nome_arquivo)){
		if (strpos($mydir, 'nvoice') !== false) {
			$tipo = "vendas";
			$caminho = "Vendas/VM Invoice 3";
			$pasta_ftp = $dir;
			$dir = str_replace("com_vminvoice3","Vm Invoice3",$dir);
		} else if (strpos($mydir, 'ompra') !== false) {
			$tipo = "compras";
			$caminho = "Compras/VM Compra 3";
			$pasta_ftp = $dir;
			$dir = str_replace("com_vmcompra3","Vm Compra3",$dir);
		} else if (strpos($mydir, 'inancas') !== false) {
			$tipo = "financas";
			$caminho = "AS Finanças/As Finanças 3";
			$pasta_ftp = $dir;
			$dir = str_replace("com_asfinancas","admin",$dir);
		} else if (strpos($mydir, 'comissao') !== false) {
			$tipo = "comissao";
			$caminho = "AS Comissao/AS Comissao3/com_comissao";
			$pasta_ftp = $dir;
			$dir = str_replace("com_comissao","admin",$dir);
		} else if (strpos($mydir, 'relatorios') !== false) {
			$tipo = "relatorios";
			$caminho = "AS Relatorios/AS Relatorios 3/com_asrelatorios";
			$pasta_ftp = $dir;
			$dir = str_replace("com_asrelatorios","admin",$dir);
		}
		if (strpos($mydir, 'Sistema') === false && $jacopiei == 0){
			replicar::copiarArquivo($mydir."/".$nome_arquivo,$plat."/Sistema/".$caminho."/".$dir."/",$nome_arquivo);
			$jacopiei = 1;
		} else {
			if ($jacopiei){
				echo "O arquivo ou pasta ".$nome_arquivo." já havia sido copiado para a pasta de sistema.\n";
			} else {
				if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false){
					$nome_pasta = str_replace(array("pasta=","Pasta="),"",$nome_arquivo);
					$src = $mydir."/".$nome_pasta;
					$dst = $plat."/Sistema/".$caminho."/".$dir."/".$nome_pasta;
					replicar::copiarDiretorio($src, $dst, $nome_pasta, 1);
					$jacopiei = 1;			
				} else {
					echo "O arquivo ".$nome_arquivo." nao foi copiado para a pasta de sistema.\n";
				}
			}		
		}
	} 
	if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false){
		$nome_pasta = str_replace(array("pasta=","Pasta="),"",$nome_arquivo);
		$pasta_ftp = "/httpdocs/solucaom-fora/".$pastas_ftp_meio.$dir."/".$nome_pasta;
		echo "Enviando a pasta inteira ".$nome_pasta." ao FTP...\n";
		replicar::enviarPastaAoFTP("vps4.nunesti.com","grupointernet","3Caras&1Fera#",$mydir."/".$nome_pasta, $pasta_ftp, $mydir);		
	} else {
        replicar::enviarArquivoAoFTP("vps4.nunesti.com", "grupointernet", "3Caras&1Fera#", $mydir, "/httpdocs/solucaom-fora/".$pastas_ftp_meio.$pasta_ftp, $nome_arquivo, null, $mydir);
    }
}
if ($solucao == "n"){
	$sistema = "s";
}

echo "\n\nNSMart\n";
$nsmart = "";
/*while($nsmart != "s" && $nsmart != "S" && $nsmart != "n" && $nsmart != "N"){
	$nsmart = prompt("Deseja atualizar o NSmart?");	
}*/
if ($excessao_alguns){
	foreach ($excessao_alguns as $exc){
		if (strpos($pesq, $exc) == true) {
			echo "Arquivo na Excessão! Não é possível replicar o arquivo ".$pesq." para o NSMart\n";
			$sistema = "n";
			$nsmart = "n";
		}
	}
}
if ($excessao_exclusiva[$tipo]["nsmart"]) {
    foreach ($excessao_exclusiva[$tipo]["nsmart"] as $exc_exclusiva) {
		if (strpos($pesq, $exc_exclusiva) == true) {
			echo "Arquivo na Excessão! Não é possível replicar o arquivo ".$pesq." para o NSmart\n";
			$sistema = "n";
			$nsmart = "n";
		}
    }
}
$dir = strpos($mydir, $ano."/");
$dir = substr($mydir, $dir);
$dir = str_replace($ano."/", "", $dir);
//if ($nsmart == "S" || $nsmart == "s"){
if ($sistema == "S" || $sistema == "s"){
	if (file_exists($mydir."/".$nome_arquivo)){
		if (strpos($mydir, 'nvoice') !== false) {
			$tipo = "vendas";
			$caminho = "Vendas/VM Invoice 3";
			$pasta_ftp = $dir;
			$dir = str_replace("com_vminvoice3","Vm Invoice3",$dir);
		} else if (strpos($mydir, 'ompra') !== false) {
			$tipo = "compras";
			$caminho = "Compras/VM Compra 3";
			$pasta_ftp = $dir;
			$dir = str_replace("com_vmcompra3","Vm Compra3",$dir);
		} else if (strpos($mydir, 'inancas') !== false) {
			$tipo = "financas";
			$caminho = "AS Finanças/As Finanças 3";
			$pasta_ftp = $dir;
			$dir = str_replace("com_asfinancas","admin",$dir);
		} else if (strpos($mydir, 'comissao') !== false) {
			$tipo = "comissao";
			$caminho = "AS Comissao/AS Comissao3/com_comissao";
			$pasta_ftp = $dir;
			$dir = str_replace("com_comissao","admin",$dir);
		} else if (strpos($mydir, 'relatorios') !== false) {
			$tipo = "relatorios";
			$caminho = "AS Relatorios/AS Relatorios 3/com_asrelatorios";
			$pasta_ftp = $dir;
			$dir = str_replace("com_asrelatorios","admin",$dir);
		} else if (strpos($mydir, 'servicos') !== false) {
			$tipo = "servicos";
			$caminho = "VM Servicos/admin";
			$pasta_ftp = $dir;
			$dir = str_replace("com_vmservicos/","",$dir);			
		}
		if (strpos($mydir, 'Sistema') === false && $jacopiei == 0){
			replicar::copiarArquivo($mydir."/".$nome_arquivo,$plat."/Sistema/".$caminho."/".$dir."/",$nome_arquivo);
			$jacopiei = 1;
		} else {
			if ($jacopiei){
				echo "O arquivo ou pasta ".$nome_arquivo." já havia sido copiado para a pasta de sistema.\n";
			} else {
				if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false){
					$nome_pasta = str_replace(array("pasta=","Pasta="),"",$nome_arquivo);
					$src = $mydir."/".$nome_pasta;
					$dst = $plat."/Sistema/".$caminho."/".$dir."/".$nome_pasta;
					replicar::copiarDiretorio($src, $dst, $nome_pasta, 1);
					$jacopiei = 1;			
				} else {
					echo "O arquivo ".$nome_arquivo." nao foi copiado para a pasta de sistema.\n";
				}
			}		
		}
	} 
	if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false){
		$nome_pasta = str_replace(array("pasta=","Pasta="),"",$nome_arquivo);
		$pasta_ftp = "/httpdocs/site/".$pastas_ftp_meio.$dir."/".$nome_pasta;
		echo "Enviando a pasta inteira ".$nome_pasta." ao FTP...\n";
		replicar::enviarPastaAoFTP("vps4.nunesti.com","nsmart","3Caras&1Fera#",$mydir."/".$nome_pasta, $pasta_ftp, $mydir);		
	} else {
        replicar::enviarArquivoAoFTP("vps4.nunesti.com", "nsmart", "3Caras&1Fera#", $mydir, "/httpdocs/site/".$pastas_ftp_meio.$pasta_ftp, $nome_arquivo, null, $mydir);
    }
}
if ($nsmart == "n"){
	$sistema = "s";
}

foreach ($sites as $site){
    echo "\n\n".$site["Nome"]."\n";
    $sitevar = "";
    /*
	while($sitevar != "s" && $sitevar != "S" && $sitevar != "n" && $sitevar != "N"){
        $nsmart = prompt("Deseja atualizar o NSmart?");
    }
	*/
	if ($excessao_alguns){
		foreach ($excessao_alguns as $exc){
			if (strpos($pesq, $exc) == true) {
				echo "Arquivo na Excessão! Não é possível replicar o arquivo ".$pesq." para o ".$site["Nome"]."\n";
				$sistema = "n";				
				$pausa = "s";
			}
		}
	}
	if ($excessao_exclusiva[$tipo][$site["alias"]]) {
		foreach ($excessao_exclusiva[$tipo][$site["alias"]] as $exc_exclusiva) {
			if (strpos($pesq, $exc_exclusiva) == true) {
				echo "Arquivo na Excessão! Não é possível replicar o arquivo ".$pesq." para o ".$site["Nome"]."\n";
				$sistema = "n";
				$pausa = "s";
			}
		}
	}
    $dir = strpos($mydir, $ano . "/");
    $dir = substr($mydir, $dir);
    $dir = str_replace($ano . "/", "", $dir);
    //if ($sitevar == "S" || $sitevar == "s"){
	if ($sistema == "S" || $sistema == "s") {
        if (file_exists($mydir . "/" . $nome_arquivo)) {
            if (strpos($mydir, 'nvoice') !== false) {
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
				$dir = str_replace("com_vmservicos/","",$dir);			
			} else if (strpos($mydir, 'virtuemart') !== false) {
				$tipo = "virtuemart";
				$caminho = "Site com sistema para instalação/Site Completo/".$pastas_ftp_meio;
				$pasta_ftp = $dir;
			}
            if (strpos($mydir, 'Sistema') === false && $jacopiei == 0) {
                replicar::copiarArquivo($mydir . "/" . $nome_arquivo, $plat . "/Sistema/" . $caminho . "/" . $dir . "/", $nome_arquivo);
                $jacopiei = 1;
            } else {
                if ($jacopiei) {
                    echo "O arquivo ou pasta " . $nome_arquivo . " já havia sido copiado para a pasta de sistema.\n";
                } else {
					if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false){
						$nome_pasta = str_replace(array("pasta=","Pasta="),"",$nome_arquivo);
						$src = $mydir."/".$nome_pasta;
						$dst = $plat."/Sistema/".$caminho."/".$dir."/".$nome_pasta;
						replicar::copiarDiretorio($src, $dst, $nome_pasta, 1);
						$jacopiei = 1;			
					} else {
						echo "O arquivo ".$nome_arquivo." nao foi copiado para a pasta de sistema.\n";
					}                   
                }
            }
        }
		if (strpos($nome_arquivo, "pasta") !== false || strpos($nome_arquivo, "Pasta") !== false){
            $nome_pasta = str_replace(array("pasta=","Pasta="),"",$nome_arquivo);
			$pasta_ftp = "/httpdocs/".$site["ftp_pasta_do_site"]."/".$pastas_ftp_meio.$dir."/".$nome_pasta;
			echo "Enviando a pasta inteira ".$nome_pasta." ao FTP...\n";
			replicar::enviarPastaAoFTP($site["ftp_host"], $site["ftp_user_login"], $site["ftp_pass"], $mydir."/".$nome_pasta, $pasta_ftp, $mydir);		
        } else {
            replicar::enviarArquivoAoFTP($site["ftp_host"], $site["ftp_user_login"], $site["ftp_pass"], $mydir, "/httpdocs/".$site["ftp_pasta_do_site"]."/".$pastas_ftp_meio . $pasta_ftp, $nome_arquivo, null, $mydir);
        }
    }
	if ($pausa == "s"){
		$pausa = "n";
		$sistema = "s";
	}
}
echo "Replicacao terminada!\n";
if ($win){
	prompt( "Pausa");
}
?>