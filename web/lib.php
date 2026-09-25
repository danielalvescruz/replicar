<?php
// Versão web do Replicar - a MESMA pasta roda em dois lugares e descobre sozinha onde está:
//
// - Máquina do Daniel: aberta pelo "executar replicar_web.sh" (servidor embutido do PHP, php -S, só em
//   127.0.0.1). Sem senha. Roda o replicar.php / replicar_bd.php como processo separado (proc_open) e
//   trabalha tanto com as pastas locais quanto com as do servidor do Ricardo (via SSH). Lista: sites.json.
//
// - Servidor do Ricardo: https://dev.aguiarsoftware.com.br/replicar/web/ (nginx + PHP 5.6, sem proc_open).
//   Pede senha (endereço público). Carrega o replicar.php / replicar_bd.php dentro da página e responde as
//   perguntas pelos ganchos replicar_web_resposta() / replicar_web_sql(). Lista: .sites.json (nome com
//   ponto: o nginx não deixa baixar pela web).
//
// Tudo aqui precisa funcionar no PHP 5.6 do site do Ricardo (por isso valor() no lugar de "??").

define('MODO_SERVIDOR', PHP_SAPI !== 'cli-server' && PHP_SAPI !== 'cli');
define('REPLICAR_PHP_LOCAL', dirname(__DIR__) . '/replicar.php');
define('REPLICAR_BD_LOCAL', dirname(__DIR__) . '/replicar_bd.php');
define('REPLICAR_PHP_REMOTO', '/home/ricardo/web/dev.aguiarsoftware.com.br/public_html/replicar/replicar.php');
define('SSH_ALIAS_REMOTO', 'ricardo-bay');
define('ARQUIVO_SITES', dirname(__DIR__) . (MODO_SERVIDOR ? '/.sites.json' : '/sites.json'));
define('ARQUIVO_SENHA', dirname(__DIR__) . '/.senha_web');

// Este arquivo só é carregado pelas páginas; aberto direto pelo navegador não responde nada
if (MODO_SERVIDOR && realpath(valor($_SERVER, 'SCRIPT_FILENAME', '')) === __FILE__) {
    http_response_code(404);
    exit;
}

// ---------------------------------------------------------------------------------------------
// Login (só no servidor do Ricardo - na máquina local a página só é acessível pelo 127.0.0.1)
// ---------------------------------------------------------------------------------------------

function iniciarSessao()
{
    if (session_id() === '') {
        session_name('replicar_web');
        $https = !empty($_SERVER['HTTPS']) || valor($_SERVER, 'HTTP_X_FORWARDED_PROTO', '') === 'https';
        session_set_cookie_params(0, rtrim(dirname(valor($_SERVER, 'SCRIPT_NAME', '/')), '/') . '/', '', $https, true);
        session_start();
    }
}

function logado()
{
    if (!MODO_SERVIDOR) {
        return true;
    }
    iniciarSessao();
    return !empty($_SESSION['replicar_logado']);
}

function conferirSenha($senha)
{
    $hash = trim((string) @file_get_contents(ARQUIVO_SENHA));
    return $hash !== '' && password_verify($senha, $hash);
}

// Páginas: sem login vai pra tela de login
function exigirLogin()
{
    if (!logado()) {
        header('Location: login.php');
        exit;
    }
}

// ---------------------------------------------------------------------------------------------
// Lista de sites (sites.json) - a mesma usada pelo replicar.php e pelo replicar_bd.php no Konsole
// ---------------------------------------------------------------------------------------------

function lerSites()
{
    $d = json_decode((string) @file_get_contents(ARQUIVO_SITES), true);
    return (is_array($d) && isset($d['sites']) && is_array($d['sites'])) ? $d['sites'] : array();
}

// $lista[$chave] ou o padrão, se não existir (o mesmo que "??", que o PHP 5.6 do site do Ricardo não entende)
function valor($lista, $chave, $padrao = null)
{
    return (is_array($lista) && isset($lista[$chave])) ? $lista[$chave] : $padrao;
}

// Minúsculas sem depender da extensão mbstring (que não está instalada em todo lugar)
function minusculas($texto)
{
    return function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower(strtr($texto, array('Á' => 'á', 'À' => 'à', 'Â' => 'â', 'Ã' => 'ã', 'É' => 'é', 'Ê' => 'ê', 'Í' => 'í', 'Ó' => 'ó', 'Ô' => 'ô', 'Õ' => 'õ', 'Ú' => 'ú', 'Ç' => 'ç')));
}

// Identificador de um site novo a partir do nome (ex: "Edições IFEN" -> "edicoes_ifen")
function gerarId($nome, $usados)
{
    $ascii = strtr($nome, array('á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o',
        'õ' => 'o', 'ú' => 'u', 'ç' => 'c', 'Á' => 'a', 'À' => 'a', 'Â' => 'a', 'Ã' => 'a', 'É' => 'e', 'Ê' => 'e', 'Í' => 'i', 'Ó' => 'o',
        'Ô' => 'o', 'Õ' => 'o', 'Ú' => 'u', 'Ç' => 'c'));
    $base = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($ascii)), '_') ?: 'site';
    $id = $base;
    for ($n = 2; isset($usados[$id]); $n++) {
        $id = $base . '_' . $n;
    }
    return $id;
}

// Confere a lista vinda da tela "Sites". Retorna a lista limpa ou, em $erros, o que está errado.
function validarSites($lista, &$erros)
{
    $erros = array();
    $limpa = array();
    $ids = array();
    $nomes = array();
    $referencias = 0;
    $texto = function ($v) { return trim((string) $v); };

    foreach ((array) $lista as $i => $s) {
        $n = $i + 1;
        $nome = $texto(valor($s, 'nome', ''));
        $id = $texto(valor($s, 'id', ''));
        if ($id === '') {
            $id = gerarId($nome, $ids);
        }
        if ($nome === '') {
            $erros[] = "Site $n: falta o nome.";
            continue;
        }
        if (!preg_match('/^[a-z0-9_]+$/', $id)) {
            $erros[] = "$nome: identificador inválido.";
        }
        if (isset($ids[$id])) {
            $erros[] = "$nome: identificador repetido ($id).";
        }
        if (isset($nomes[minusculas($nome)])) {
            $erros[] = "Nome repetido: $nome.";
        }
        $ids[$id] = true;
        $nomes[minusculas($nome)] = true;

        $site = array('id' => $id, 'nome' => $nome, 'ativo' => !empty($s['ativo']), 'arquivos' => null, 'banco' => null);

        if (!empty($s['arquivos'])) {
            $a = $s['arquivos'];
            $site['arquivos'] = array(
                'perguntar'        => !empty($a['perguntar']),
                'junto_com'        => empty($a['perguntar']) ? $texto(valor($a, 'junto_com', 'sistema')) : '',
                'protocolo'        => (valor($a, 'protocolo', '')) === 'sftp' ? 'sftp' : 'ftp',
                'host'             => $texto(valor($a, 'host', '')),
                'usuario'          => $texto(valor($a, 'usuario', '')),
                'senha'            => (string) (valor($a, 'senha', '')),
                'pasta_remota'     => $texto(valor($a, 'pasta_remota', '')),
                'copia_local'      => in_array(valor($a, 'copia_local', ''), array('cliente', 'sistema'), true) ? $a['copia_local'] : 'nenhuma',
                'pasta_cliente'    => $texto(valor($a, 'pasta_cliente', '')),
                'alias'            => $texto(valor($a, 'alias', '')),
                'ignorar_excecoes' => !empty($a['ignorar_excecoes']),
                'somente_tipo'     => $texto(valor($a, 'somente_tipo', '')),
            );
            foreach (array('host' => 'servidor', 'usuario' => 'usuário', 'pasta_remota' => 'pasta no servidor') as $campo => $rotulo) {
                if ($site['arquivos'][$campo] === '') {
                    $erros[] = "$nome (arquivos): falta o $rotulo.";
                }
            }
            if ($site['arquivos']['copia_local'] === 'cliente' && $site['arquivos']['pasta_cliente'] === '') {
                $erros[] = "$nome (arquivos): falta a pasta do cliente pra cópia local.";
            }
        }

        if (!empty($s['banco'])) {
            $b = $s['banco'];
            $site['banco'] = array(
                'host'       => $texto(valor($b, 'host', '')),
                'usuario'    => $texto(valor($b, 'usuario', '')),
                'senha'      => (string) (valor($b, 'senha', '')),
                'banco'      => $texto(valor($b, 'banco', '')),
                'prefixo'    => $texto(valor($b, 'prefixo', '')),
                'usar'       => in_array(valor($b, 'usar', ''), array('sim', 'perguntar', 'nao'), true) ? $b['usar'] : 'sim',
                'referencia' => !empty($b['referencia']),
            );
            foreach (array('host' => 'servidor', 'usuario' => 'usuário', 'banco' => 'nome do banco', 'prefixo' => 'prefixo') as $campo => $rotulo) {
                if ($site['banco'][$campo] === '') {
                    $erros[] = "$nome (banco): falta o $rotulo.";
                }
            }
            if ($site['banco']['referencia']) {
                $referencias++;
            }
        }
        $limpa[] = $site;
    }

    foreach ($limpa as $site) {
        $a = $site['arquivos'];
        if ($a && !$a['perguntar'] && !isset($ids[$a['junto_com']])) {
            $erros[] = $site['nome'] . ': "vai junto com" aponta pra um site que não existe.';
        }
    }
    if ($referencias > 1) {
        $erros[] = 'Só um site pode ser a referência do banco de dados.';
    }
    return $limpa;
}

// Grava o sites.json guardando uma cópia do anterior em sites.json.bak
function salvarSites($lista)
{
    $json = json_encode(array('sites' => array_values($lista)), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    if (file_exists(ARQUIVO_SITES)) {
        @copy(ARQUIVO_SITES, ARQUIVO_SITES . '.bak');
    }
    $tmp = ARQUIVO_SITES . '.tmp';
    if (file_put_contents($tmp, $json . "\n") === false) {
        return false;
    }
    if (!rename($tmp, ARQUIVO_SITES)) {
        return false;
    }
    if (MODO_SERVIDOR) {
        // O site grava como "ricardo" e o terminal lê como "daniel" (grupo ricardo)
        @chmod(ARQUIVO_SITES, 0660);
    }
    return true;
}

// Sites ativos com envio de arquivos, na ordem em que o replicar.php roda
function sitesArquivos()
{
    $lista = array();
    foreach (lerSites() as $s) {
        if (!empty($s['ativo']) && !empty($s['arquivos'])) {
            $lista[] = $s;
        }
    }
    return $lista;
}

// Sites que o replicar.php pergunta - viram os checkboxes da página
function destinos()
{
    $todos = sitesArquivos();
    $lista = array();
    foreach ($todos as $s) {
        if (empty($s['arquivos']['perguntar'])) {
            continue;
        }
        $juntos = array();
        foreach ($todos as $o) {
            if (empty($o['arquivos']['perguntar']) && $o['arquivos']['junto_com'] === $s['id']) {
                $juntos[] = $o['nome'];
            }
        }
        $obs = '';
        if ($juntos) {
            $obs = 'Também envia para ' . implode(', ', $juntos);
        }
        if (!empty($s['arquivos']['somente_tipo'])) {
            $obs = 'Só para arquivos do tipo "' . $s['arquivos']['somente_tipo'] . '"';
        }
        $lista[] = array(
            'id'       => $s['id'],
            'nome'     => $s['nome'],
            'pergunta' => 'Deseja atualizar ' . $s['nome'] . '?',
            'obs'      => $obs,
        );
    }
    return $lista;
}

// Etapas das barras de progresso. "inicio" é o título que o replicar.php escreve ao começar o site;
// "destino" é o checkbox que faz o site rodar (os que não perguntam seguem outro site, ex: o Sistema).
function etapas()
{
    $lista = array();
    foreach (sitesArquivos() as $s) {
        $a = $s['arquivos'];
        $lista[] = array(
            'id'       => $s['id'],
            'nome'     => $s['nome'],
            'inicio'   => $s['nome'],
            'destino'  => !empty($a['perguntar']) ? $s['id'] : ($a['junto_com'] ?: 'sistema'),
            'especial' => !empty($a['somente_tipo']),
        );
    }
    return $lista;
}

// Acompanha a saída do replicar.php e descobre em que site ele está e quanto já foi feito,
// separado em dois canais: "local" (cópia pras pastas de backup / SFTP pro Note 1) e "ftp" (envio pro site).
// O progresso vem das linhas "@@PROGRESSO {...}" que o replicar_class.php escreve quando
// REPLICAR_PROGRESSO=1. Se a replicar_class.php não tiver essas linhas (ex: servidor ainda com a
// versão antiga), usa as mensagens de texto como plano B.
class Progresso
{
    private $atual = null;

    private static function canalVazio()
    {
        return array(
            'marcadores' => false, 'ok' => 0, 'erro' => 0, 'msg' => '', 'rodando' => false,
            'arquivos' => 0, 'arquivos_feitos' => 0,           // envio/cópia de pasta inteira
            'bytes' => 0, 'bytes_feitos' => 0, 'bytes_arquivo' => 0,
        );
    }

    // Retorna os eventos gerados por essa linha e, em $mostrar, se a linha vai pro log
    public function linha($linha, &$mostrar = true)
    {
        $eventos = array();
        $texto = trim($linha);
        $mostrar = true;

        if (strpos($texto, '@@PROGRESSO ') === 0) {
            $mostrar = false;
            $dados = json_decode(substr($texto, 12), true);
            if ($this->atual && is_array($dados)) {
                $this->marcador($dados);
                $eventos[] = $this->evento('enviando');
            }
            return $eventos;
        }

        foreach (etapas() as $e) {
            if ($texto === $e['inicio']) {
                if ($this->atual) {
                    $eventos[] = $this->finalizar();
                }
                $this->atual = array('id' => $e['id'], 'excecao' => 0, 'msg' => '',
                    'local' => self::canalVazio(), 'ftp' => self::canalVazio());
                $eventos[] = $this->evento('enviando');
                return $eventos;
            }
        }

        if (strpos($texto, 'Replicacao terminada!') === 0 || preg_match('/^Replicacao de .* terminada!/u', $texto)) {
            if ($this->atual) {
                $eventos[] = $this->finalizar();
            }
            return $eventos;
        }

        if (!$this->atual || $texto === '') {
            return $eventos;
        }

        $local = &$this->atual['local'];
        $ftp = &$this->atual['ftp'];

        if (strpos($texto, 'Arquivo na Excessão') === 0) {
            $this->atual['excecao']++;
            $this->atual['msg'] = $texto;
            return $eventos;
        }
        // Mensagens do próprio replicar.php quando a cópia local não é feita (não é erro):
        // já está na pasta daquele site, ou já foi copiado pra pasta Sistema por outro site
        if (preg_match('/nao foi copiado|não foi copiado|já havia sido copiado/u', $texto)) {
            $local['msg'] = $texto;
            $eventos[] = $this->evento('enviando');
            return $eventos;
        }

        // Plano B, sem as linhas de progresso
        if (!$local['marcadores']) {
            if (preg_match('/^Não foi possível copiar|^Pasta de destino .* não existe|^Diretorio para copiar nao existe|^Nao pode copiar a pasta|^A pasta principal/u', $texto)) {
                $local['erro']++;
                $local['msg'] = $texto;
                $eventos[] = $this->evento('enviando');
            } elseif (preg_match('/ copiado para |copiados com sucesso/u', $texto)) {
                $local['ok']++;
                $eventos[] = $this->evento('enviando');
            }
        }
        if (!$ftp['marcadores']) {
            if (preg_match('/^Erro: (Login FTP|Não foi possível conectar|Não foi possível se conectar)|^Não foi possível enviar|^Não é possível entrar na pasta ftp|^Erro ao enviar/u', $texto)) {
                $ftp['erro']++;
                $ftp['msg'] = $texto;
                $eventos[] = $this->evento('enviando');
            } elseif (preg_match('/enviado (ao FTP|via SFTP)/u', $texto)) {
                $ftp['ok']++;
                $eventos[] = $this->evento('enviando');
            }
        }
        return $eventos;
    }

    private function marcador($d)
    {
        $c = (valor($d, 'c', 'ftp')) === 'local' ? 'local' : 'ftp';
        $a = &$this->atual[$c];
        $a['marcadores'] = true;
        switch (valor($d, 'e', '')) {
            case 'pasta':
                $a['arquivos'] = (int) $d['arquivos'];
                $a['bytes'] = (int) $d['bytes'];
                $a['arquivos_feitos'] = 0;
                $a['bytes_feitos'] = 0;
                break;
            case 'inicio':
                $a['rodando'] = true;
                $a['bytes_arquivo'] = 0;
                if (!$a['arquivos']) {
                    $a['bytes'] = (int) $d['tamanho'];
                    $a['bytes_feitos'] = 0;
                }
                $a['msg'] = $d['arquivo'];
                break;
            case 'bytes':
                $a['bytes_arquivo'] = (int) $d['enviados'];
                break;
            case 'fim':
                $a['rodando'] = false;
                $a['bytes_arquivo'] = 0;
                if (!empty($d['ok'])) {
                    $a['ok']++;
                    $a['bytes_feitos'] += (int) $d['tamanho'];
                    if ($a['arquivos']) {
                        $a['arquivos_feitos']++;
                    }
                }
                break;
            case 'erro':
                $a['erro']++;
                $a['msg'] = $d['msg'];
                break;
        }
    }

    // Fecha o site em andamento quando o processo termina ou é cancelado
    public function fim($cancelado = false)
    {
        if (!$this->atual) {
            return null;
        }
        return $cancelado ? $this->evento('cancelado') : $this->finalizar();
    }

    private function finalizar()
    {
        $a = $this->atual;
        $erros = $a['local']['erro'] + $a['ftp']['erro'];
        $oks = $a['local']['ok'] + $a['ftp']['ok'];
        if ($erros && $oks) {
            $estado = 'parcial';
        } elseif ($erros) {
            $estado = 'erro';
        } elseif ($oks) {
            $estado = 'ok';
        } elseif ($a['excecao']) {
            $estado = 'excecao';
        } else {
            $estado = 'pulado';
        }
        $ev = $this->evento($estado, true);
        $this->atual = null;
        return $ev;
    }

    // Situação de um canal: aguardando / enviando / ok / erro / parcial / pulado / cancelado
    private static function estadoCanal($c, $site, $final)
    {
        if ($site === 'cancelado' && ($c['rodando'] || (!$c['ok'] && !$c['erro']))) {
            return 'cancelado';
        }
        if ($c['erro']) {
            return ($final && $c['ok']) ? 'parcial' : 'erro';
        }
        if ($c['rodando'] || ($c['arquivos'] && $c['arquivos_feitos'] < $c['arquivos'] && !$final)) {
            return 'enviando';
        }
        if ($c['ok']) {
            return 'ok';
        }
        return $final ? 'pulado' : 'aguardando';
    }

    private static function resumoCanal($c, $site, $final)
    {
        // Percentual pelos bytes; null = não dá pra saber (barra animada)
        $pct = null;
        if ($c['marcadores'] && $c['bytes'] > 0) {
            $pct = (int) min(100, round(($c['bytes_feitos'] + $c['bytes_arquivo']) / $c['bytes'] * 100));
        } elseif ($c['ok']) {
            $pct = 100;
        }
        $estado = self::estadoCanal($c, $site, $final);
        $msg = $c['msg'];
        if ($estado === 'ok' && !$c['arquivos']) {
            $msg = '';
        }
        return array('estado' => $estado, 'pct' => $pct, 'arquivos' => $c['arquivos'],
            'arquivos_feitos' => $c['arquivos_feitos'], 'msg' => $msg);
    }

    private function evento($estado, $final = false)
    {
        $a = $this->atual;
        $final = $final || $estado === 'cancelado';
        return array(
            't'      => 'etapa',
            'id'     => $a['id'],
            'estado' => $estado,
            'msg'    => $a['msg'],
            'local'  => self::resumoCanal($a['local'], $estado, $final),
            'ftp'    => self::resumoCanal($a['ftp'], $estado, $final),
        );
    }
}

// Interpreta o caminho colado (ou recebido do script da pasta) e descobre:
// - se é local ou no servidor do Ricardo
// - a pasta onde o replicar.php deve rodar
// - o arquivo, quando o caminho colado já aponta pra um arquivo
function interpretarCaminho($caminho)
{
    return MODO_SERVIDOR ? interpretarCaminhoServidor($caminho) : interpretarCaminhoLocal($caminho);
}

// No servidor do Ricardo tudo é pasta do próprio servidor. Aceita o caminho normal, a URL do Dolphin
// (sftp://daniel@100.96.10.71/home/ricardo/...) ou o caminho do KIO-Fuse.
function interpretarCaminhoServidor($caminho)
{
    $caminho = trim(trim($caminho), "\"'");
    if (preg_match('#^(sftp|fish|ssh)://#i', $caminho)) {
        $caminho = rawurldecode((string) parse_url($caminho, PHP_URL_PATH));
    } elseif (strpos($caminho, 'kio-fuse') !== false && preg_match('#/sftp/[^/]+(/.*)$#', $caminho, $m)) {
        $caminho = $m[1];
    }
    $caminho = rtrim(preg_replace('#^file://#', '', $caminho), '/');
    if ($caminho === '') {
        return array('erro' => 'Informe o caminho da pasta ou do arquivo.');
    }
    // O site só enxerga as pastas dentro do public_html dele
    $raiz = dirname(dirname(__DIR__));
    if (strpos($caminho . '/', $raiz . '/') !== 0) {
        return array('erro' => 'Só dá pra replicar pastas dentro de ' . $raiz);
    }
    if (!file_exists($caminho)) {
        return array('erro' => 'Caminho não encontrado no servidor: ' . $caminho);
    }
    $arquivo = '';
    $pasta = $caminho;
    if (is_file($caminho)) {
        $arquivo = basename($caminho);
        $pasta = dirname($caminho);
    }
    return array('remoto' => false, 'onde' => 'Servidor do Ricardo', 'pasta' => $pasta, 'arquivo' => $arquivo);
}

// Na máquina do Daniel: pasta local, ou pasta do servidor do Ricardo (sftp://, KIO-Fuse ou /home/ricardo/...)
function interpretarCaminhoLocal($caminho)
{
    $caminho = trim($caminho);
    $caminho = trim($caminho, "\"'");
    $caminho = preg_replace('#^file://#', '', $caminho);
    $caminho = rtrim($caminho, '/');
    if ($caminho === '') {
        return array('erro' => 'Informe o caminho da pasta ou do arquivo.');
    }

    $remoto = false;
    $local_acessivel = false;
    $caminho_fuse = '';

    if (preg_match('#^(sftp|fish|ssh)://#i', $caminho)) {
        // URL do Dolphin, ex: sftp://ricardo-bay/home/ricardo/web/...
        $remoto = true;
        $caminho = rawurldecode((string) parse_url($caminho, PHP_URL_PATH));
    } elseif (strpos($caminho, 'kio-fuse') !== false && preg_match('#/sftp/[^/]+(/.*)$#', $caminho, $m)) {
        // Pasta remota aberta pelo Dolphin (montada via KIO-Fuse) - mesma regra do replicar_php.sh
        $remoto = true;
        $local_acessivel = file_exists($caminho);
        $caminho_fuse = $caminho;
        $caminho = $m[1];
    } elseif (!file_exists($caminho) && strpos($caminho, '/home/ricardo/') === 0) {
        $remoto = true;
    }

    $arquivo = '';
    $pasta = $caminho;
    if ($remoto) {
        $eh_arquivo = $local_acessivel ? is_file($caminho_fuse) : (bool) preg_match('#/[^/]+\.[A-Za-z0-9]{1,5}$#', $caminho);
    } else {
        if (!file_exists($caminho)) {
            return array('erro' => 'Caminho não encontrado: ' . $caminho);
        }
        $eh_arquivo = is_file($caminho);
    }
    if ($eh_arquivo) {
        $arquivo = basename($caminho);
        $pasta = dirname($caminho);
    }

    return array(
        'remoto'  => $remoto,
        'onde'    => $remoto ? 'Servidor do Ricardo (SSH)' : 'Local',
        'pasta'   => $pasta,
        'arquivo' => $arquivo,
    );
}

function comandoReplicar($info)
{
    if ($info['remoto']) {
        // Sem -t: as respostas vão pela entrada padrão, não precisa de terminal.
        // BatchMode evita travar pedindo senha caso a chave falhe.
        $remoto = 'cd ' . escapeshellarg($info['pasta']) . ' && REPLICAR_PROGRESSO=1 php ' . escapeshellarg(REPLICAR_PHP_REMOTO);
        return array(array('ssh', '-o', 'BatchMode=yes', SSH_ALIAS_REMOTO, $remoto), null);
    }
    return array(array(PHP_BINARY, REPLICAR_PHP_LOCAL), $info['pasta']);
}

// Decide a resposta pra uma linha de saída do replicar.php. Retorna null quando a linha não é pergunta.
function responder($linha, $modo, $arquivo, $marcados, &$aviso)
{
    $aviso = '';
    $linha = trim($linha);

    if (strpos($linha, 'Deseja escrever o nome do arquivo') === 0) {
        return $arquivo;
    }
    if (strpos($linha, 'Verifique se está tudo ok') === 0) {
        // Na análise sai aqui, antes de enviar qualquer coisa
        return $modo === 'analisar' ? 's' : '';
    }
    if ($linha === 'Pausa') {
        return '';
    }
    foreach (destinos() as $d) {
        if ($linha === $d['pergunta']) {
            return in_array($d['id'], $marcados, true) ? 's' : 'n';
        }
    }
    if (strpos($linha, 'Deseja ') === 0 && substr($linha, -1) === '?') {
        $aviso = 'Pergunta nova que a versão web não conhece, respondido "n": ' . $linha;
        return 'n';
    }
    return null;
}

// ---------------------------------------------------------------------------------------------
// Replicar banco de dados (replicar_bd.php)
// ---------------------------------------------------------------------------------------------

// Sites ativos com banco, na ordem em que o replicar_bd.php roda (a referência primeiro)
function sitesBanco()
{
    $lista = array();
    foreach (lerSites() as $s) {
        if (empty($s['ativo']) || empty($s['banco'])) {
            continue;
        }
        if (!empty($s['banco']['referencia'])) {
            array_unshift($lista, $s);
        } else {
            $lista[] = $s;
        }
    }
    return $lista;
}

function responderBanco($linha, $marcados, $atual_id)
{
    $texto = trim($linha);
    if (preg_match('/^Deseja executar em \d+ site\(s\)\? \(s\/N\):$/u', $texto)) {
        return 's';
    }
    if (preg_match('/^Executar neste site\? \(s\/N\):$/u', $texto)
        || preg_match('/^Confirmar execução .*\(s\/N\):$/u', $texto)) {
        return in_array($atual_id, $marcados, true) ? 's' : 'n';
    }
    return null;
}

// Acompanha a saída do replicar_bd.php: em que site está e quantos comandos SQL já rodaram
class ProgressoBanco
{
    private $atual = null;
    private $ids = array();

    public function __construct()
    {
        foreach (lerSites() as $s) {
            $this->ids[$s['nome']] = $s['id'];
        }
    }

    public function atualId()
    {
        return $this->atual ? $this->atual['id'] : null;
    }

    public function linha($linha)
    {
        $eventos = array();
        $texto = trim($linha);

        if (preg_match('/^-> \[(.+)\]$/u', $texto, $m)) {
            if ($this->atual) {
                $eventos[] = $this->finalizar();
            }
            $nome = preg_replace('/ \(REFERÊNCIA\)$/u', '', $m[1]);
            $this->atual = array('id' => valor($this->ids, $nome, $nome), 'total' => 0, 'feitos' => 0, 'erros' => 0,
                'pulado' => false, 'rodando' => false, 'msg' => '');
            $eventos[] = $this->evento('aguardando');
            return $eventos;
        }
        if (strpos($texto, 'Resultado:') === 0) {
            if ($this->atual) {
                $eventos[] = $this->finalizar();
            }
            return $eventos;
        }
        if (!$this->atual) {
            return $eventos;
        }

        $a = &$this->atual;
        if (preg_match('/^SQL a executar \((\d+) comando/u', $texto, $m)) {
            $a['total'] = (int) $m[1];
        } elseif (strpos($texto, 'PULADO') === 0) {
            $a['pulado'] = true;
            $a['msg'] = $texto;
            $eventos[] = $this->finalizar();
        } elseif (strpos($texto, 'ERRO de conexão') === 0) {
            $a['erros']++;
            $a['msg'] = $texto;
            $eventos[] = $this->finalizar();
        } elseif (preg_match('/^\[\d+\] Executando/u', $texto)) {
            $a['rodando'] = true;
            $a['feitos']++;
            if (strpos($texto, 'ERRO') !== false) {
                $a['erros']++;
                $a['msg'] = substr($texto, strpos($texto, 'ERRO'));
            }
            $eventos[] = $this->evento('enviando');
        }
        return $eventos;
    }

    public function fim($cancelado = false)
    {
        if (!$this->atual) {
            return null;
        }
        if ($cancelado) {
            $ev = $this->evento('cancelado');
            $this->atual = null;
            return $ev;
        }
        return $this->finalizar();
    }

    private function finalizar()
    {
        $a = $this->atual;
        $oks = $a['feitos'] - $a['erros'];
        if ($a['pulado']) {
            $estado = 'pulado';
        } elseif ($a['erros'] && $oks > 0) {
            $estado = 'parcial';
        } elseif ($a['erros']) {
            $estado = 'erro';
        } elseif ($a['feitos']) {
            $estado = 'ok';
        } else {
            $estado = 'pulado';
        }
        $ev = $this->evento($estado);
        $this->atual = null;
        return $ev;
    }

    private function evento($estado)
    {
        $a = $this->atual;
        return array(
            't'      => 'etapa',
            'id'     => $a['id'],
            'estado' => $estado,
            'feitos' => $a['feitos'],
            'total'  => $a['total'],
            'pct'    => $a['total'] ? (int) round(min($a['feitos'], $a['total']) / $a['total'] * 100) : null,
            'msg'    => $a['msg'],
        );
    }
}
