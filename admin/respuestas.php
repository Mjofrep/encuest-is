<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireRole(['admin', 'analista', 'lector']);

$pdo = db();

$campanaId = (int)($_GET['campana_id'] ?? 0);
$sentimiento = trim($_GET['sentimiento'] ?? '');
$msg = trim($_GET['msg'] ?? '');

$where = [];
$params = [];

if ($campanaId > 0) {
    $where[] = "r.campana_id = ?";
    $params[] = $campanaId;
}
if ($sentimiento !== '') {
    $where[] = "a.sentimiento = ?";
    $params[] = $sentimiento;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
SELECT 
    r.id,
    r.fecha_respuesta,
    r.analizado_ia,
    c.nombre AS campana,
    r.calificacion,
    r.respuesta_2,
    r.comentario,
    r.sucursal,
    r.canal,
    a.sentimiento,
    a.tema_principal,
    a.urgencia
FROM fb_respuestas r
JOIN fb_campanas c ON c.id = r.campana_id
LEFT JOIN fb_analisis_ia a ON a.respuesta_id = r.id
$whereSql
ORDER BY r.fecha_respuesta DESC
LIMIT 200
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$respuestas = $stmt->fetchAll();

$campanas = $pdo->query("SELECT id, nombre FROM fb_campanas ORDER BY nombre")->fetchAll();

$pageTitle = 'Respuestas';
require __DIR__ . '/../includes/header_admin.php';

$queryActual = $_SERVER['QUERY_STRING'] ?? '';
$volver = 'respuestas.php' . ($queryActual !== '' ? '?' . $queryActual : '');
?>

<div class="container mb-5">
  <?php if ($msg === 'ia_ok'): ?>
    <div class="alert alert-success">El análisis IA se ejecutó correctamente.</div>
  <?php elseif ($msg === 'ia_error'): ?>
    <div class="alert alert-danger">No se pudo ejecutar el análisis IA.</div>
  <?php elseif ($msg === 'error_id'): ?>
    <div class="alert alert-warning">ID de respuesta no válido.</div>
  <?php elseif ($msg === 'no_encontrado'): ?>
    <div class="alert alert-warning">La respuesta no fue encontrada.</div>
  <?php endif; ?>

  <div class="card rounded-4 mb-4">
    <div class="card-body">
      <form class="row g-3">
        <div class="col-md-5">
          <label class="form-label">Campaña</label>
          <select name="campana_id" class="form-select">
            <option value="0">Todas</option>
            <?php foreach ($campanas as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $campanaId === (int)$c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-5">
          <label class="form-label">Sentimiento</label>
          <select name="sentimiento" class="form-select">
            <option value="">Todos</option>
            <option value="positivo" <?= $sentimiento === 'positivo' ? 'selected' : '' ?>>Positivo</option>
            <option value="neutro" <?= $sentimiento === 'neutro' ? 'selected' : '' ?>>Neutro</option>
            <option value="negativo" <?= $sentimiento === 'negativo' ? 'selected' : '' ?>>Negativo</option>
          </select>
        </div>
        <div class="col-md-2 align-self-end">
          <button class="btn btn-primary w-100">
            <i class="bi bi-search me-2"></i>Filtrar
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="card rounded-4">
    <div class="card-body">
      <h5 class="section-title mb-3">Feedback recibidos</h5>

      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Campaña</th>
              <th>Cal.</th>
              <th>Mejor</th>
              <th>Mejorar</th>
              <th>Sentimiento</th>
              <th>Tema</th>
              <th>Urgencia</th>
              <th>Analizado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$respuestas): ?>
              <tr><td colspan="10" class="text-center text-muted">No hay respuestas.</td></tr>
            <?php else: ?>
              <?php foreach ($respuestas as $r): ?>
                <?php
                  $sent = $r['sentimiento'] ?? '';
                  $cls = 'badge-soft-secondary';

                  if ($sent === 'positivo') $cls = 'badge-soft-success';
                  elseif ($sent === 'negativo') $cls = 'badge-soft-danger';
                  elseif ($sent === 'mixto') $cls = 'badge-soft-warning';

                  $yaAnalizado = (int)($r['analizado_ia'] ?? 0) === 1;
                ?>
                <tr>
                  <td><?= htmlspecialchars($r['fecha_respuesta']) ?></td>
                  <td><?= htmlspecialchars($r['campana']) ?></td>
                  <td><span class="badge text-bg-primary"><?= (int)$r['calificacion'] ?></span></td>
                  <td class="comment-preview"><?= htmlspecialchars($r['respuesta_2']) ?></td>
                  <td class="comment-preview"><?= htmlspecialchars($r['comentario']) ?></td>
                  <td><span class="badge <?= $cls ?>"><?= htmlspecialchars($sent ?: 'Sin análisis') ?></span></td>
                  <td><?= htmlspecialchars($r['tema_principal'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($r['urgencia'] ?? '—') ?></td>
                  <td>
                    <?php if ($yaAnalizado): ?>
                      <span class="badge badge-soft-success">Sí</span>
                    <?php else: ?>
                      <span class="badge badge-soft-warning">Pendiente</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="d-flex gap-1 flex-wrap">
                      <a href="detalle_feedback.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver detalle">
                        <i class="bi bi-eye"></i>
                      </a>

                      <?php if (userHasRole('admin') || userHasRole('analista')): ?>
                        <form method="post" action="procesar_ia_respuesta.php" class="d-inline">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                          <input type="hidden" name="volver" value="<?= htmlspecialchars($volver) ?>">
                          <button
                            type="submit"
                            class="btn btn-sm <?= $yaAnalizado ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                            title="<?= $yaAnalizado ? 'Reanalizar con IA' : 'Analizar con IA' ?>"
                            onclick="return confirm('¿Deseas <?= $yaAnalizado ? 'reanalizar' : 'analizar' ?> esta respuesta con IA?');"
                          >
                            <i class="bi <?= $yaAnalizado ? 'bi-arrow-repeat' : 'bi-cpu' ?>"></i>
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
