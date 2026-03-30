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
$sucursal     = trim($_POST['sucursal'] ?? '');
$canal        = trim($_POST['canal'] ?? '');

if ($campanaId <= 0) {
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

$stmt = $pdo->prepare("SELECT * FROM fb_preguntas WHERE campana_id = ? ORDER BY orden ASC, id ASC");
$stmt->execute([$campanaId]);
$preguntas = $stmt->fetchAll();

if (!$preguntas) {
    header('Location: formulario.php?token=' . urlencode($token) . '&error=1');
    exit;
}

$opcionesPorPregunta = [];
$idsOpciones = [];
foreach ($preguntas as $p) {
    if (($p['tipo'] ?? '') === 'opcion') {
        $idsOpciones[] = (int)$p['id'];
    }
}

if ($idsOpciones) {
    $placeholders = implode(',', array_fill(0, count($idsOpciones), '?'));
    $stmt = $pdo->prepare("SELECT * FROM fb_preguntas_opciones WHERE pregunta_id IN ($placeholders) ORDER BY orden ASC, id ASC");
    $stmt->execute($idsOpciones);
    $opciones = $stmt->fetchAll();
    foreach ($opciones as $opcion) {
        $pid = (int)$opcion['pregunta_id'];
        $opcionesPorPregunta[$pid][] = $opcion;
    }
}

$calificacion = null;
$respuesta2 = '';
$comentario = '';
$detalle = [];
$hayEscala = false;

foreach ($preguntas as $pregunta) {
    $pid = (int)$pregunta['id'];
    $tipo = (string)$pregunta['tipo'];
    $obligatoria = (int)$pregunta['obligatoria'] === 1;
    $campo = 'pregunta_' . $pid;
    $valor = $_POST[$campo] ?? null;

    if (is_string($valor)) {
        $valor = trim($valor);
    }

    if ($obligatoria && ($valor === null || $valor === '')) {
        header('Location: formulario.php?token=' . urlencode($token) . '&error=1');
        exit;
    }

    if ($tipo === 'escala') {
        $hayEscala = true;
        $valorInt = (int)$valor;
        if ($valor !== null && ($valorInt < 1 || $valorInt > 5)) {
            header('Location: formulario.php?token=' . urlencode($token) . '&error=1');
            exit;
        }

        if ($calificacion === null && $valorInt > 0) {
            $calificacion = $valorInt;
        }

        if ($valorInt > 0) {
            $detalle[] = [
                'pregunta_id' => $pid,
                'respuesta_texto' => null,
                'respuesta_opcion' => null,
                'respuesta_escala' => $valorInt
            ];
        }
    } elseif ($tipo === 'texto') {
        $texto = is_string($valor) ? trim($valor) : '';
        if ($obligatoria && $texto === '') {
            header('Location: formulario.php?token=' . urlencode($token) . '&error=1');
            exit;
        }

        if ($texto !== '') {
            if ($respuesta2 === '') {
                $respuesta2 = $texto;
            } elseif ($comentario === '') {
                $comentario = $texto;
            }

            $detalle[] = [
                'pregunta_id' => $pid,
                'respuesta_texto' => $texto,
                'respuesta_opcion' => null,
                'respuesta_escala' => null
            ];
        }
    } elseif ($tipo === 'opcion') {
        $opcionId = (int)$valor;
        $opciones = $opcionesPorPregunta[$pid] ?? [];
        $textoOpcion = '';
        foreach ($opciones as $opcion) {
            if ((int)$opcion['id'] === $opcionId) {
                $textoOpcion = (string)$opcion['texto_opcion'];
                break;
            }
        }

        if ($obligatoria && $opcionId <= 0) {
            header('Location: formulario.php?token=' . urlencode($token) . '&error=1');
            exit;
        }

        if ($opcionId > 0 && $textoOpcion === '') {
            header('Location: formulario.php?token=' . urlencode($token) . '&error=1');
            exit;
        }

        if ($opcionId > 0) {
            $detalle[] = [
                'pregunta_id' => $pid,
                'respuesta_texto' => null,
                'respuesta_opcion' => $textoOpcion,
                'respuesta_escala' => null
            ];
        }
    }
}

if ($hayEscala && $calificacion === null) {
    header('Location: formulario.php?token=' . urlencode($token) . '&error=1');
    exit;
}

if ($calificacion === null) {
    $calificacion = 0;
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

        if ($detalle) {
            $stmtDetalle = $pdo->prepare("INSERT INTO fb_respuestas_detalle (respuesta_id, pregunta_id, respuesta_texto, respuesta_opcion, respuesta_escala, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            foreach ($detalle as $item) {
                $stmtDetalle->execute([
                    $respuestaId,
                    (int)$item['pregunta_id'],
                    $item['respuesta_texto'],
                    $item['respuesta_opcion'],
                    $item['respuesta_escala']
                ]);
            }
        }

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
