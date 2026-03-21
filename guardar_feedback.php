<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$pdo = db();

$campanaId   = (int)($_POST['campana_id'] ?? 0);
$token       = trim($_POST['token'] ?? '');
$calificacion = (int)($_POST['calificacion'] ?? 0);
$respuesta2   = trim($_POST['respuesta_2'] ?? '');
$comentario   = trim($_POST['comentario'] ?? '');
$sucursal     = trim($_POST['sucursal'] ?? '');
$canal        = trim($_POST['canal'] ?? '');

if ($campanaId <= 0 || $calificacion < 1 || $calificacion > 5 || $respuesta2 === '' || $comentario === '') {
    header('Location: formulario.php?token=' . urlencode($token) . '&error=1');
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM fb_campanas WHERE id = ? AND estado = 'activa' LIMIT 1");
$stmt->execute([$campanaId]);
$campana = $stmt->fetch();

if (!$campana) {
    header('Location: index.php?token=' . urlencode($token));
    exit;
}

try {
    $pdo->beginTransaction();

    $sql = "INSERT INTO fb_respuestas
            (campana_id, fecha_respuesta, calificacion, respuesta_2, comentario, sucursal, canal, analizado_ia)
            VALUES
            (?, NOW(), ?, ?, ?, ?, ?, 0)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $campanaId,
        $calificacion,
        $respuesta2,
        $comentario,
        $sucursal !== '' ? $sucursal : null,
        $canal !== '' ? $canal : null
    ]);

    $respuestaId = (int)$pdo->lastInsertId();

    $pdo->commit();
require_once __DIR__ . '/api/analizar_feedback.php';
analizarFeedbackConIA($respuestaId);
    /*
      Más adelante aquí puedes llamar:
      require_once __DIR__ . '/api/analizar_feedback.php';
      analizarFeedback($respuestaId);
    */

    header('Location: gracias.php?id=' . $respuestaId);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "<h3>Error al guardar feedback</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
}