<?php
// ============================================================
// /api/set_objective.php
// Salva o objetivo ativo na sessão PHP (persiste entre páginas)
// Chamado pelo <form> do sidebar ao mudar o select
// ============================================================
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

startSession();
if (!isLoggedIn()) { header('Location: /florescer/public/index.php'); exit; }

$id = (int)($_POST['objective_id'] ?? 0);
$_SESSION['active_objective'] = $id > 0 ? $id : null;

// Volta para a página que enviou o form (ou para o dashboard)
$ref = $_SERVER['HTTP_REFERER'] ?? '/florescer/public/views/dashboard.php';

// Segurança: só redireciona para URLs internas
if (strpos($ref, $_SERVER['HTTP_HOST']) === false) {
    $ref = '/florescer/public/views/dashboard.php';
}

header("Location: $ref");
exit;