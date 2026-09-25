<?php
require __DIR__ . '/lib.php';
iniciarSessao();

if (isset($_GET['sair'])) {
    $_SESSION = array();
    session_destroy();
    header('Location: login.php');
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pequena espera contra tentativas de adivinhar a senha
    sleep(1);
    if (conferirSenha((string) valor($_POST, 'senha', ''))) {
        session_regenerate_id(true);
        $_SESSION['replicar_logado'] = true;
        header('Location: index.php');
        exit;
    }
    $erro = 'Senha incorreta.';
}
if (logado()) {
    header('Location: index.php');
    exit;
}
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Replicar - entrar</title>
<link rel="stylesheet" href="estilo.css">
</head>
<body>
<main style="max-width:380px; padding-top:12vh">
    <h1>Replicar</h1>
    <form method="post" class="card">
        <label class="titulo" for="senha">Senha</label>
        <input type="password" id="senha" name="senha" style="width:100%" autofocus required>
        <?php if ($erro): ?><div class="dica" style="color:var(--err)"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
        <div style="margin-top:12px"><button class="principal" type="submit">Entrar</button></div>
    </form>
</main>
</body>
</html>
