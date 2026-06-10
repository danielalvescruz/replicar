<?php
class replicar
{
    public function prompt($msg)
    {
        echo $msg . "\n";
        ob_flush();
        $in = trim(fgets(STDIN));
        return $in;
    }

    function enviarPastaAoFTP($ftp_server, $ftp_user_name, $ftp_user_pass, $path, $pasta_ftp, $mydir){
        $conn_id = ftp_connect($ftp_server);
        $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);

        if (!file_exists($path)){
          echo "O diretório local: ".$path." nao existe.\n";
          return;
        }

        $dir = opendir($path);
        
        if (!@ftp_chdir($conn_id, $pasta_ftp)) {
          ftp_mkdir($conn_id, $pasta_ftp);
        }
        ftp_chdir($conn_id,$pasta_ftp);

         // Percorre todos os arquivos e subpastas no diretório local
        while (($file = readdir($dir)) !== false) {
          if ($file != '.' && $file != '..') {
              $local_path = $path . '/' . $file;
              $remote_path = $pasta_ftp . '/' . $file;

              if (is_dir($local_path)) {
                  // Caso seja uma subpasta, chama a função recursivamente
                  echo "Criando a subpasta ".$file."\n";
                  self::enviarPastaAoFTP($ftp_server, $ftp_user_name, $ftp_user_pass, $local_path, $remote_path, $mydir);
              } else {
                  // Caso seja um arquivo, faz o upload
                  if (ftp_put($conn_id, $remote_path, $local_path, FTP_BINARY)){
                    echo "$file enviado ao FTP para a pasta $pasta_ftp\n";
                  } else {
                    echo "Não foi possível enviar $file ao FTP\n";
                  }
              }
          }
        }
        
        ftp_close($conn_id);
        chdir($mydir);
     }

  public function enviarArquivoAoFTP($ftp_server, $ftp_user_name, $ftp_user_pass, $path, $pasta_ftp, $file, $new_filename, $mydir)
  {
    // Aumentar o tempo limite de execução (opcional)
    set_time_limit(300); // 5 minutos

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

    // 🔧 FORÇAR MODO PASSIVO IMEDIATAMENTE APÓS LOGIN
    ftp_pasv($conn_id, true);

    // Mudar diretório local
    chdir($path);

    // Mudar diretório remoto
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

    // Validação de extensão
    if (strpos($file, '.php') === false && strpos($file, '.css') === false && strpos($file, '.js') === false && strpos($file, '.xml') === false && strpos($file, '.ini') === false && strpos($file, '.jpg') === false && strpos($file, '.png') === false && strpos($file, '.gif') === false) {
      echo "Extensão do arquivo não permitida.\n";
      ftp_close($conn_id);
      return;
    }

    $nome_arquivo = self::pegaNomeArquivo($file);

    // Enviar arquivo (agora com modo passivo garantido)
    $enviou = ftp_put($conn_id, $nome_arquivo, $file, FTP_BINARY);

    if ($enviou) {
      if ($new_filename) {
        ftp_rename($conn_id, $nome_arquivo, $new_filename);
        $nome_arquivo = $new_filename;
      }
      echo "$nome_arquivo enviado ao FTP para a pasta $pasta_ftp\n";
    } else {
      echo "Tentando enviar no modo passivo...\n";
      // O modo passivo já foi ativado, mas vamos forçar novamente
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

    public function pegaNomeArquivo($arquivo_origem)
    {
        $nome_arquivo = explode("/", $arquivo_origem);
        $nome_arquivo = end($nome_arquivo);
        return $nome_arquivo;
    }

    public function pegarUltimoArquivoModificado()
    {
        $files = array_merge(glob("*.php"), glob("*.css"), glob("*.js"),glob("*.xml"),glob("*.ini"),glob("*.jpg"),glob("*.png"));
        $files = array_combine($files, array_map("filemtime", $files));
        arsort($files);
        $latest_file = key($files);
        return $latest_file;
    }

    function copiarArquivo($arquivo_origem,$pasta_destino,$novo_nome = null){
      if (!$arquivo_origem){
        echo "Não é possível copiar um arquivo null\n";
        return false;
      }
      $nome_arquivo = explode("/",$arquivo_origem);
      $nome_arquivo = end($nome_arquivo);
      if (!file_exists($arquivo_origem)){
        echo "Não foi possível copiar ".$nome_arquivo." não existe\n";
        return false;
      }
      $nome_pasta = explode("/",$pasta_destino);
      end($nome_pasta);
      $nome_pasta = prev($nome_pasta);
      if (!$novo_nome){
        $novo_nome = $nome_arquivo;
      }
      $pasta_destino_completa = $pasta_destino.$novo_nome;
      if (!file_exists($pasta_destino)){
          echo "Pasta de destino $pasta_destino não existe\n";
          return false;
      }
      if (!file_exists($pasta_destino_completa)){
          echo "Pasta de destino $pasta_destino_completa não existe\n";
          return false;
      }
      $copiado = copy($arquivo_origem, $pasta_destino_completa);
      if ($copiado){
        echo "$novo_nome copiado para $nome_pasta\n";
        return true;
      } else {
        echo "Não foi possível copiar $novo_nome\n";
        return false;
      }
  }

  function copiarDiretorio($src, $dst, $nome_pasta, $recursivo = 0) {
    if (!is_dir($src)){
      echo "Diretorio para copiar nao existe\n";
      echo $src."\n";
      return false;
    }
    if ($src == $dst){
      echo "Nao pode copiar a pasta. A pasta de origem e destino são as mesmas.\n";
      return false;
    }
    $ano = date("Y");
    if (preg_match("#/$ano/([^/]+)/#", $dst, $matches)) {
      $parte_pasta = $matches[1];      
    }
    if ($parte_pasta == "com_vminvoice" || $parte_pasta == "com_vminvoice3" ||
      $parte_pasta == "com_vmcompra3" || $parte_pasta == "com_asfinancas" || 
      $parte_pasta == "com_asrelatorios" || $parte_pasta == "com_comissao"){
        if (preg_match("#^(.*?/$ano/[^/]+/)#", $dst, $matches)) {
          $dst_parte = $matches[1];      
        }
        if (!file_exists($dst_parte)) {
          echo "A pasta principal (".$dst_parte.") para copiar este diretorio nao existe.\n";
          return false;
        }
    }
    $dir = opendir($src);
    // Make the destination directory if not exist
    //echo $dst."\n";
    if (!file_exists($dst)) {
      mkdir($dst, 0755, true);
    }

    while( $file = readdir($dir) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
          if ( is_dir($src . '/' . $file) ) {
              if ($recursivo){
                self::copiarDiretorio($src . '/' . $file, $dst . '/' . $file,$nome_pasta,1);
                echo "Copiando a pasta ".$nome_pasta." inteira...\n";
              }
          } else {
              if (copy($src . '/' . $file, $dst . '/' . $file)){
                echo $nome_pasta." - Arquivos copiados com sucesso\n";
              }
          }
        }
    }
    closedir($dir);
  }
}
?>