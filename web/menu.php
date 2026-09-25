<?php
// Menu no topo das páginas - defina $pagina antes do include (só funciona incluído por uma página)
if (!function_exists('exigirLogin')) {
    http_response_code(404);
    exit;
}
$itens = array('replicar' => array('index.php', 'Replicar arquivos'), 'banco' => array('banco.php', 'Banco de dados'), 'sites' => array('sites.php', 'Sites'));
?>
<nav class="menu">
    <?php foreach ($itens as $id => $item): ?>
    <a href="<?= $item[0] ?>"<?= $pagina === $id ? ' class="ativo"' : '' ?>><?= $item[1] ?></a>
    <?php endforeach; ?>
    <?php if (MODO_SERVIDOR): ?><a href="login.php?sair=1" style="margin-left:auto">Sair</a><?php endif; ?>
</nav>
