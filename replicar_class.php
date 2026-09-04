<?php
// phpseclib (Net_SFTP) - usada nas funções *AoSFTP() com autenticação por chave, já que shell_exec()
// e afins estão desabilitados neste servidor (não dá pra usar lftp). Instalada via instalar_phpseclib.php
// A phpseclib 1.0 resolve seus próprios requires internos (ex: RSA.php -> Math/BigInteger.php) de forma
// relativa ao include_path do PHP, não à pasta do arquivo - por isso precisa entrar no include_path.
if (file_exists(__DIR__ . '/phpseclib1/Net/SFTP.php')) {
    set_include_path(__DIR__ . '/phpseclib1' . PATH_SEPARATOR . get_include_path());
    require_once 'Net/SFTP.php';
    require_once 'Crypt/RSA.php';
}

class replicar
{
    public static function prompt($msg)
    {
        echo $msg . "\n";
        ob_flush();
        $in = trim(fgets(STDIN));
        return $in;
    }

    public static function enviarPastaAoFTP($ftp_server, $ftp_user_name, $ftp_user_pass, $path, $pasta_ftp, $mydir)
    {
        $conn_id = ftp_connect($ftp_server);
        if (!$conn_id) {
            echo "Erro: Não foi possível conectar ao FTP: $ftp_server\n";
            return;
        }

        $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
        if (!$login_result) {
            echo "Erro: Login FTP falhou.\n";
            ftp_close($conn_id);
            return;
        }

        ftp_pasv($conn_id, true);

        if (!file_exists($path)) {
            echo "O diretório local: $path não existe.\n";
            ftp_close($conn_id);
            return;
        }

        self::_enviarPastaRecursivo($conn_id, $path, $pasta_ftp);

        ftp_close($conn_id);
        chdir($mydir);
    }

    private static function _enviarPastaRecursivo($conn_id, $path, $pasta_ftp)
    {
        if (!@ftp_chdir($conn_id, $pasta_ftp)) {
            ftp_mkdir($conn_id, $pasta_ftp);
            ftp_chdir($conn_id, $pasta_ftp);
        }

        $dir = opendir($path);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;

            $local_path  = $path . '/' . $file;
            $remote_path = $pasta_ftp . '/' . $file;

            if (is_dir($local_path)) {
                echo "Criando a subpasta: $file\n";
                self::_enviarPastaRecursivo($conn_id, $local_path, $remote_path);
            } else {
                if (ftp_put($conn_id, $remote_path, $local_path, FTP_BINARY)) {
                    echo "$file enviado ao FTP para: $pasta_ftp\n";
                } else {
                    echo "Erro ao enviar: $file para $pasta_ftp\n";
                }
            }
        }
        closedir($dir);
    }

    public static function enviarArquivoAoFTP($ftp_server, $ftp_user_name, $ftp_user_pass, $path, $pasta_ftp, $file, $new_filename, $mydir)
    {
        set_time_limit(300);

        $conn_id = ftp_connect($ftp_server);
        if (!$conn_id) {
            echo "Erro: Não foi possível se conectar ao servidor FTP: $ftp_server\n";
            return;
        }

        $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
        if (!$login_result) {
            echo "Erro: Login FTP falhou.\n";
            ftp_close($conn_id);
            return;
        }

        ftp_pasv($conn_id, true);
        chdir($path);

        if (!ftp_chdir($conn_id, $pasta_ftp)) {
            echo "Não é possível entrar na pasta ftp = " . $pasta_ftp . "\n";
            $novo_dir = ftp_pwd($conn_id);
            echo "pasta que estou no ftp = " . $novo_dir . "\n";
            ftp_close($conn_id);
            return;
        }

        if (!file_exists($file)) {
            echo "Não é possível enviar um arquivo que não existe no FTP\n";
            ftp_close($conn_id);
            return;
        }

        $extensoes_permitidas = ['php', 'css', 'js', 'xml', 'ini', 'jpg', 'png', 'gif', 'sql'];
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (!in_array($ext, $extensoes_permitidas)) {
            echo "Extensão do arquivo não permitida: .$ext\n";
            ftp_close($conn_id);
            return;
        }

        $nome_arquivo = self::pegaNomeArquivo($file);

        $enviou = ftp_put($conn_id, $nome_arquivo, $file, FTP_BINARY);

        if ($enviou) {
            if ($new_filename) {
                ftp_rename($conn_id, $nome_arquivo, $new_filename);
                $nome_arquivo = $new_filename;
            }
            echo "$nome_arquivo enviado ao FTP para a pasta $pasta_ftp\n";
        } else {
            echo "Tentando enviar no modo passivo...\n";
            ftp_pasv($conn_id, true);
            $enviou = ftp_put($conn_id, $nome_arquivo, $file, FTP_BINARY);
            if ($enviou) {
                if ($new_filename) {
                    ftp_rename($conn_id, $nome_arquivo, $new_filename);
                    $nome_arquivo = $new_filename;
                }
                echo "$nome_arquivo enviado ao FTP para a pasta $pasta_ftp\n";
            } else {
                echo "Não foi possível enviar $file para a pasta $pasta_ftp \n";
            }
        }

        ftp_close($conn_id);
        chdir($mydir);
    }

    public static function pegaNomeArquivo($arquivo_origem)
    {
        $partes = explode("/", $arquivo_origem);
        return array_pop($partes);
    }

    public static function pegarUltimoArquivoModificado()
    {
        $files = array_merge(
            glob("*.php") ?: [],
            glob("*.css") ?: [],
            glob("*.js") ?: [],
            glob("*.xml") ?: [],
            glob("*.ini") ?: [],
            glob("*.jpg") ?: [],
            glob("*.png") ?: []
        );

        if (empty($files)) {
            return false;
        }

        // Usa filectime() (mudança do inode) em vez de filemtime(), pois o SFTP/Dolphin
        // costuma preservar o mtime original do arquivo ao copiar - o ctime reflete
        // o momento real em que o arquivo chegou nesta pasta, não a data preservada.
        $files = array_combine($files, array_map("filectime", $files));
        arsort($files);
        return key($files);
    }

    public static function copiarArquivo($arquivo_origem, $pasta_destino, $novo_nome = null)
    {
        if (!$arquivo_origem) {
            echo "Não é possível copiar um arquivo null\n";
            return false;
        }

        $nome_arquivo = self::pegaNomeArquivo($arquivo_origem);

        if (!file_exists($arquivo_origem)) {
            echo "Não foi possível copiar " . $nome_arquivo . " pois não existe\n";
            return false;
        }

        $nome_pasta_arr = array_values(array_filter(explode("/", $pasta_destino)));
        $count_p = count($nome_pasta_arr);
        $nome_pasta = ($count_p >= 2) ? $nome_pasta_arr[$count_p - 2] : '';

        if (!$novo_nome) {
            $novo_nome = $nome_arquivo;
        }

        $pasta_destino_completa = rtrim($pasta_destino, '/') . '/' . $novo_nome;

        if (!file_exists($pasta_destino)) {
            echo "Pasta de destino $pasta_destino não existe\n";
            return false;
        }

        $copiado = copy($arquivo_origem, $pasta_destino_completa);
        if ($copiado) {
            echo "$novo_nome copiado para $nome_pasta\n";
            return true;
        } else {
            echo "Não foi possível copiar $novo_nome\n";
            return false;
        }
    }

    public static function copiarDiretorio($src, $dst, $nome_pasta, $recursivo = 0)
    {
        if (!is_dir($src)) {
            echo "Diretorio para copiar nao existe\n";
            echo $src . "\n";
            return false;
        }
        if ($src == $dst) {
            echo "Nao pode copiar a pasta. A pasta de origem e destino são as mesmas.\n";
            return false;
        }

        $ano = date("Y");
        $parte_pasta = "";

        if (preg_match("#/$ano/([^/]+)/#", $dst, $matches)) {
            $parte_pasta = $matches[1];
        }

        if (in_array($parte_pasta, [
            "com_vminvoice", "com_vminvoice3", "com_vmcompra3", 
            "com_asfinancas", "com_asrelatorios", "com_comissao"
        ])) {
            if (preg_match("#^(.*?/$ano/[^/]+/)#", $dst, $matches)) {
                $dst_parte = $matches[1];
                if (!file_exists($dst_parte)) {
                    echo "A pasta principal (" . $dst_parte . ") para copiar este diretorio nao existe.\n";
                    return false;
                }
            }
        }

        $dir = opendir($src);
        if (!file_exists($dst)) {
            mkdir($dst, 0755, true);
        }

        while (($file = readdir($dir)) !== false) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    if ($recursivo) {
                        self::copiarDiretorio($src . '/' . $file, $dst . '/' . $file, $nome_pasta, 1);
                        echo "Copiando a pasta " . $nome_pasta . " inteira...\n";
                    }
                } else {
                    if (copy($src . '/' . $file, $dst . '/' . $file)) {
                        echo $nome_pasta . " - Arquivos copiados com sucesso\n";
                    }
                }
            }
        }
        closedir($dir);
    }

    // Conecta e autentica via Net_SFTP (phpseclib) - por senha, ou por chave RSA quando $ssh_key_path
    // é informado. Usado tanto por enviarArquivoAoSFTP() quanto enviarPastaAoSFTP().
    private static function conectarSFTP($ftp_server, $ftp_user_name, $ftp_user_pass, $ssh_key_path)
    {
        if (!class_exists('Net_SFTP')) {
            echo "Erro: phpseclib não está instalada (rode instalar_phpseclib.php).\n";
            return false;
        }

        $sftp = new Net_SFTP($ftp_server);

        if ($ssh_key_path) {
            if (!file_exists($ssh_key_path)) {
                echo "Erro: chave SSH não encontrada em $ssh_key_path\n";
                return false;
            }
            $key = new Crypt_RSA();
            $key->loadKey(file_get_contents($ssh_key_path));
            $logado = $sftp->login($ftp_user_name, $key);
        } else {
            $logado = $sftp->login($ftp_user_name, $ftp_user_pass);
        }

        if (!$logado) {
            echo "Erro: login SFTP falhou em $ftp_server\n";
            return false;
        }

        return $sftp;
    }

    public static function enviarArquivoAoSFTP($ftp_server, $ftp_user_name, $ftp_user_pass, $path, $pasta_ftp, $file, $new_filename, $mydir, $ssh_key_path = null)
    {
        set_time_limit(300);

        if (!file_exists($path . '/' . $file)) {
            echo "Não é possível enviar um arquivo que não existe: $file\n";
            return;
        }

        $exts = ['.php', '.css', '.js', '.xml', '.ini', '.jpg', '.png', '.gif'];
        $valido = false;
        foreach ($exts as $ext) {
            if (strpos($file, $ext) !== false) {
                $valido = true;
                break;
            }
        }
        if (!$valido) {
            echo "Extensão do arquivo não permitida.\n";
            return;
        }

        $sftp = self::conectarSFTP($ftp_server, $ftp_user_name, $ftp_user_pass, $ssh_key_path);
        if (!$sftp) {
            chdir($mydir);
            return;
        }

        $nome_arquivo = self::pegaNomeArquivo($file);
        $destino_final = $new_filename ? $new_filename : $nome_arquivo;
        $local_file = $path . '/' . $file;

        // Garante que a pasta de destino exista (backups locais podem cair em pasta ainda não criada)
        $sftp->mkdir($pasta_ftp, -1, true);

        if ($sftp->put($pasta_ftp . '/' . $destino_final, $local_file, NET_SFTP_LOCAL_FILE)) {
            echo "$destino_final enviado via SFTP para $pasta_ftp\n";
        } else {
            echo "Não foi possível enviar $destino_final para $pasta_ftp\n";
        }

        chdir($mydir);
    }

    public static function enviarPastaAoSFTP($ftp_server, $ftp_user_name, $ftp_user_pass, $path, $pasta_ftp, $mydir, $ssh_key_path = null)
    {
        if (!file_exists($path)) {
            echo "O diretório local: $path não existe.\n";
            return;
        }

        $sftp = self::conectarSFTP($ftp_server, $ftp_user_name, $ftp_user_pass, $ssh_key_path);
        if (!$sftp) {
            chdir($mydir);
            return;
        }

        self::_enviarPastaSFTPRecursivo($sftp, $path, $pasta_ftp);
        echo "Pasta enviada via SFTP para $pasta_ftp\n";

        chdir($mydir);
    }

    private static function _enviarPastaSFTPRecursivo($sftp, $path, $pasta_ftp)
    {
        $sftp->mkdir($pasta_ftp, -1, true);

        $dir = opendir($path);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;

            $local_path  = $path . '/' . $file;
            $remote_path = $pasta_ftp . '/' . $file;

            if (is_dir($local_path)) {
                self::_enviarPastaSFTPRecursivo($sftp, $local_path, $remote_path);
            } else {
                if ($sftp->put($remote_path, $local_path, NET_SFTP_LOCAL_FILE)) {
                    echo "$file enviado via SFTP para $pasta_ftp\n";
                } else {
                    echo "Erro ao enviar $file para $pasta_ftp\n";
                }
            }
        }
        closedir($dir);
    }
}
?>