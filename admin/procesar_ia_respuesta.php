<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../api/analizar_feedback.php';

$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$volver = trim($_GET['volver'] ?? 'respuestas.php');

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

$destino = $volver !== '' ? $volver : 'respuestas.php';

if ($ok) {
    header('Location: ' . $destino . (str_contains($destino, '?') ? '&' : '?') . 'msg=ia_ok');
    exit;
}

header('Location: ' . $destino . (str_contains($destino, '?') ? '&' : '?') . 'msg=ia_error');
exit;