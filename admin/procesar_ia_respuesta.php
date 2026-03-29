<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../api/analizar_feedback.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireRole(['admin', 'analista']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: respuestas.php?msg=error_id');
    exit;
}

$csrf = $_POST['csrf_token'] ?? null;
if (!csrfValidate(is_string($csrf) ? $csrf : null)) {
    header('Location: respuestas.php?msg=ia_error');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$volver = trim($_POST['volver'] ?? 'respuestas.php');

if ($id <= 0) {
    header('Location: respuestas.php?msg=error_id');
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM fb_respuestas WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    header('Location: respuestas.php?msg=no_encontrado');
    exit;
}

$ok = analizarFeedbackConIA($id);

$user = currentUser();
if ($user) {
    auditLog((int)$user['id'], 'analisis_ia', 'respuesta_id=' . $id . ' ok=' . ($ok ? '1' : '0'));
}

$destino = $volver !== '' ? $volver : 'respuestas.php';

if ($ok) {
    header('Location: ' . $destino . (str_contains($destino, '?') ? '&' : '?') . 'msg=ia_ok');
    exit;
}

header('Location: ' . $destino . (str_contains($destino, '?') ? '&' : '?') . 'msg=ia_error');
exit;
