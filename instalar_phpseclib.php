<?php
/**
 * instalar_phpseclib.php
 *
 * Baixa a phpseclib 1.x (branch/tag 1.0, a versão "PEAR-style" sem
 * namespaces: Crypt_*, Net_SFTP, etc.) diretamente do GitHub e instala
 * na pasta phpseclib1/ ao lado deste script.
 *
 * Uso:
 *   - Via navegador: instalar_phpseclib.php?confirmar=1
 *   - Via CLI:       php instalar_phpseclib.php confirmar
 *
 * IMPORTANTE: apague este arquivo do servidor depois de instalar.
 * Ele baixa e executa código de terceiros (mesmo que confiável),
 * então não deve ficar acessível publicamente por muito tempo.
 */

// ----------------------------------------------------------------------
// Configuração
// ----------------------------------------------------------------------

const PHPSECLIB_TAG      = '1.0.20';
const PHPSECLIB_ZIP_URL  = 'https://github.com/phpseclib/phpseclib/archive/refs/tags/' . PHPSECLIB_TAG . '.zip';
const PHPSECLIB_TARGET   = __DIR__ . '/phpseclib1';

// ----------------------------------------------------------------------
// Saída (funciona tanto no navegador quanto no CLI)
// ----------------------------------------------------------------------

$isCli = (PHP_SAPI === 'cli');

function out(string $msg, string $tipo = 'info'): void
{
    global $isCli;
    if ($isCli) {
        echo $msg . "\n";
        return;
    }
    static $primeira = true;
    if ($primeira) {
        echo '<!doctype html><meta charset="utf-8"><title>Instalar phpseclib1</title>';
        echo '<style>body{font-family:monospace;background:#111;color:#ddd;padding:2rem;line-height:1.5}
        .ok{color:#6f6}.erro{color:#f66}.aviso{color:#fd6}.info{color:#9cf}</style>';
        echo '<pre>';
        $primeira = false;
    }
    $cor = ['ok' => 'ok', 'erro' => 'erro', 'aviso' => 'aviso', 'info' => 'info'][$tipo] ?? 'info';
    echo '<span class="' . $cor . '">' . htmlspecialchars($msg) . '</span>' . "\n";
    @flush();
}

function falhar(string $msg): void
{
    out('ERRO: ' . $msg, 'erro');
    exit(1);
}

// ----------------------------------------------------------------------
// Confirmação (evita instalação acidental se o arquivo for acessado)
// ----------------------------------------------------------------------

global $isCli;
$confirmado = $isCli
    ? in_array('confirmar', $argv ?? [], true)
    : (($_GET['confirmar'] ?? '') === '1');

if (!$confirmado) {
    out('Instalador da phpseclib 1.x (tag ' . PHPSECLIB_TAG . ')', 'info');
    out('Destino: ' . PHPSECLIB_TARGET, 'info');
    out('');
    out('Nada foi alterado ainda. Para instalar, rode novamente com confirmação:', 'aviso');
    out($isCli ? '  php ' . basename(__FILE__) . ' confirmar' : '  ' . basename(__FILE__) . '?confirmar=1', 'aviso');
    exit(0);
}

// ----------------------------------------------------------------------
// Checagens de ambiente
// ----------------------------------------------------------------------

out('Verificando ambiente...', 'info');

if (PHP_VERSION_ID < 50303) {
    falhar('phpseclib 1.x requer PHP >= 5.3.3. Versão atual: ' . PHP_VERSION);
}
out('PHP ' . PHP_VERSION . ' OK', 'ok');

if (!extension_loaded('zip')) {
    falhar('A extensão "zip" (ZipArchive) não está disponível no PHP deste servidor.');
}
out('Extensão zip OK', 'ok');

$temExtensaoCurl = extension_loaded('curl');
$temUrlFopen     = (bool) ini_get('allow_url_fopen');
if (!$temExtensaoCurl && !$temUrlFopen) {
    falhar('É preciso ter a extensão curl ou allow_url_fopen habilitado para baixar o pacote.');
}
out('Método de download: ' . ($temExtensaoCurl ? 'curl' : 'allow_url_fopen'), 'ok');

// ----------------------------------------------------------------------
// Download do pacote
// ----------------------------------------------------------------------

out('');
out('Baixando ' . PHPSECLIB_ZIP_URL . ' ...', 'info');

$arquivoZip = tempnam(sys_get_temp_dir(), 'phpseclib_') . '.zip';

function baixarArquivo(string $url, string $destino, bool $usarCurl): void
{
    if ($usarCurl) {
        $ch = curl_init($url);
        $fp = fopen($destino, 'wb');
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_USERAGENT      => 'instalar_phpseclib.php',
            CURLOPT_FAILONERROR    => true,
        ]);
        $sucesso = curl_exec($ch);
        $erro = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        if (!$sucesso) {
            @unlink($destino);
            falhar('Falha no download via curl: ' . $erro);
        }
        return;
    }

    $contexto = stream_context_create(['http' => ['timeout' => 120, 'follow_location' => 1]]);
    $conteudo = @file_get_contents($url, false, $contexto);
    if ($conteudo === false) {
        falhar('Falha no download via file_get_contents.');
    }
    file_put_contents($destino, $conteudo);
}

baixarArquivo(PHPSECLIB_ZIP_URL, $arquivoZip, $temExtensaoCurl);

if (!file_exists($arquivoZip) || filesize($arquivoZip) === 0) {
    falhar('O arquivo baixado está vazio ou não foi salvo.');
}
out('Download concluído (' . round(filesize($arquivoZip) / 1024) . ' KB)', 'ok');

// ----------------------------------------------------------------------
// Extração
// ----------------------------------------------------------------------

out('');
out('Extraindo pacote...', 'info');

$dirTemp = sys_get_temp_dir() . '/phpseclib_extract_' . uniqid();
mkdir($dirTemp, 0755, true);

$zip = new ZipArchive();
if ($zip->open($arquivoZip) !== true) {
    falhar('Não foi possível abrir o arquivo ZIP baixado.');
}
$zip->extractTo($dirTemp);
$zip->close();
unlink($arquivoZip);

// O ZIP do GitHub extrai para uma pasta "phpseclib-<tag>/phpseclib/"
$candidatos = glob($dirTemp . '/phpseclib-*/phpseclib', GLOB_ONLYDIR);
if (empty($candidatos)) {
    falhar('Estrutura inesperada dentro do ZIP baixado (pasta "phpseclib" não encontrada).');
}
$origem = $candidatos[0];
out('Pacote extraído em: ' . $origem, 'ok');

// ----------------------------------------------------------------------
// Backup da instalação anterior (se existir)
// ----------------------------------------------------------------------

function copiarRecursivo(string $origem, string $destino): void
{
    if (!is_dir($destino)) {
        mkdir($destino, 0755, true);
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($origem, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $caminhoDestino = $destino . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($caminhoDestino)) {
                mkdir($caminhoDestino, 0755, true);
            }
        } else {
            copy($item->getPathname(), $caminhoDestino);
        }
    }
}

function removerRecursivo(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

out('');
if (is_dir(PHPSECLIB_TARGET)) {
    $backup = PHPSECLIB_TARGET . '_backup_' . date('Ymd_His');
    rename(PHPSECLIB_TARGET, $backup);
    out('Pasta existente movida para backup: ' . basename($backup), 'aviso');
}

// ----------------------------------------------------------------------
// Instalação
// ----------------------------------------------------------------------

out('Copiando arquivos para ' . PHPSECLIB_TARGET . ' ...', 'info');
copiarRecursivo($origem, PHPSECLIB_TARGET);
out('Arquivos copiados.', 'ok');

// Limpeza da pasta temporária de extração
removerRecursivo($dirTemp);

// ----------------------------------------------------------------------
// Verificação final
// ----------------------------------------------------------------------

out('');
$bootstrap = PHPSECLIB_TARGET . '/bootstrap.php';
$sftpClass = PHPSECLIB_TARGET . '/Net/SFTP.php';

if (file_exists($bootstrap) && file_exists($sftpClass)) {
    out('phpseclib 1.x (tag ' . PHPSECLIB_TAG . ') instalada com sucesso em:', 'ok');
    out('  ' . PHPSECLIB_TARGET, 'ok');
    out('');
    out('Exemplo de uso:', 'info');
    out("  require '" . PHPSECLIB_TARGET . "/Net/SFTP.php';", 'info');
    out("  \$sftp = new Net_SFTP('host');", 'info');
} else {
    falhar('Instalação concluída, mas arquivos esperados não foram encontrados. Verifique manualmente.');
}

out('');
out('IMPORTANTE: apague este arquivo (' . basename(__FILE__) . ') do servidor agora.', 'aviso');

if (!$isCli) {
    echo '</pre>';
}
