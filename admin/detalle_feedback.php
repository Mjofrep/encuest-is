<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

$pdo = db();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
SELECT 
    r.*,
    c.nombre AS campana,
    a.sentimiento,
    a.tema_principal,
    a.tema_secundario,
    a.urgencia,
    a.resumen,
    a.accion_sugerida
FROM fb_respuestas r
JOIN fb_campanas c ON c.id = r.campana_id
LEFT JOIN fb_analisis_ia a ON a.respuesta_id = r.id
WHERE r.id = ?
LIMIT 1
");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    echo "Registro no encontrado";
    exit;
}

$stmtDet = $pdo->prepare("
    SELECT tipo, fragmento, tema
    FROM fb_analisis_ia_detalle
    WHERE respuesta_id = ?
    ORDER BY id ASC
");
$stmtDet->execute([$id]);
$detalles = $stmtDet->fetchAll();

$hallazgosPositivos = [];
$hallazgosNegativos = [];

foreach ($detalles as $d) {
    if (($d['tipo'] ?? '') === 'positivo') {
        $hallazgosPositivos[] = $d;
    } elseif (($d['tipo'] ?? '') === 'negativo') {
        $hallazgosNegativos[] = $d;
    }
}
$pageTitle = 'Detalle Feedback';
require __DIR__ . '/../includes/header_admin.php';

function badgeClass(?string $sent): string {
    return match ($sent) {
        'positivo' => 'badge-soft-success',
        'negativo' => 'badge-soft-danger',
        'mixto'    => 'badge-soft-warning',
        default    => 'badge-soft-secondary',
    };
}
?>

<div class="container mb-5">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="section-title mb-0">Detalle de feedback</h4>
    <a href="respuestas.php" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-2"></i>Volver
    </a>
  </div>

  <div class="card rounded-4 mb-4">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <small class="text-muted">Fecha</small>
          <div class="fw-semibold"><?= htmlspecialchars($row['fecha_respuesta']) ?></div>
        </div>
        <div class="col-md-3">
          <small class="text-muted">Campaña</small>
          <div class="fw-semibold"><?= htmlspecialchars($row['campana']) ?></div>
        </div>
        <div class="col-md-3">
          <small class="text-muted">Calificación</small>
          <div><span class="badge text-bg-primary"><?= (int)$row['calificacion'] ?></span></div>
        </div>
        <div class="col-md-3">
          <small class="text-muted">Sentimiento</small>
          <div><span class="badge <?= badgeClass($row['sentimiento'] ?? null) ?>"><?= htmlspecialchars($row['sentimiento'] ?? 'Sin análisis') ?></span></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card rounded-4 mb-4">
    <div class="card-body">
      <h6 class="section-title mb-3">Respuesta del usuario</h6>

      <div class="mb-3">
        <small class="text-muted">¿Qué fue lo mejor de tu experiencia?</small>
        <div class="soft-box mt-1"><?= nl2br(htmlspecialchars($row['respuesta_2'] ?? '')) ?></div>
      </div>

      <div class="mb-3">
        <small class="text-muted">¿Qué deberíamos mejorar?</small>
        <div class="soft-box mt-1"><?= nl2br(htmlspecialchars($row['comentario'] ?? '')) ?></div>
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <small class="text-muted">Sucursal / Área</small>
          <div class="fw-semibold"><?= htmlspecialchars($row['sucursal'] ?? '—') ?></div>
        </div>
        <div class="col-md-6">
          <small class="text-muted">Canal</small>
          <div class="fw-semibold"><?= htmlspecialchars($row['canal'] ?? '—') ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card rounded-4">
    <div class="card-body">
      <h6 class="section-title mb-3">Análisis IA</h6>

      <div class="row g-3">
        <div class="col-md-4">
          <div class="soft-box">
            <small class="text-muted">Tema principal</small>
            <div class="fw-semibold"><?= htmlspecialchars($row['tema_principal'] ?? 'Sin análisis') ?></div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="soft-box">
            <small class="text-muted">Tema secundario</small>
            <div class="fw-semibold"><?= htmlspecialchars($row['tema_secundario'] ?? '—') ?></div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="soft-box">
            <small class="text-muted">Urgencia</small>
            <div class="fw-semibold"><?= htmlspecialchars($row['urgencia'] ?? '—') ?></div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="soft-box">
            <small class="text-muted">Resumen</small>
            <div class="fw-semibold"><?= nl2br(htmlspecialchars($row['resumen'] ?? 'Sin análisis generado')) ?></div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="soft-box">
            <small class="text-muted">Acción sugerida</small>
            <div class="fw-semibold"><?= nl2br(htmlspecialchars($row['accion_sugerida'] ?? 'Sin sugerencia generada')) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card rounded-4 mt-4">
  <div class="card-body">
    <h6 class="section-title mb-3">Hallazgos por fragmento</h6>

    <div class="row g-4">
      <div class="col-md-6">
        <div class="soft-box h-100">
          <h6 class="text-success mb-3">
            <i class="bi bi-hand-thumbs-up me-2"></i>Hallazgos positivos
          </h6>

          <?php if (empty($hallazgosPositivos)): ?>
            <div class="text-muted">No se detectaron hallazgos positivos.</div>
          <?php else: ?>
            <div class="d-flex flex-column gap-3">
              <?php foreach ($hallazgosPositivos as $h): ?>
                <div class="border rounded-3 p-3 bg-white">
                  <div class="fw-semibold"><?= nl2br(htmlspecialchars($h['fragmento'])) ?></div>
                  <small class="text-muted">
                    Tema: <?= htmlspecialchars($h['tema'] ?: 'Sin clasificar') ?>
                  </small>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-md-6">
        <div class="soft-box h-100">
          <h6 class="text-danger mb-3">
            <i class="bi bi-hand-thumbs-down me-2"></i>Hallazgos negativos
          </h6>

          <?php if (empty($hallazgosNegativos)): ?>
            <div class="text-muted">No se detectaron hallazgos negativos.</div>
          <?php else: ?>
            <div class="d-flex flex-column gap-3">
              <?php foreach ($hallazgosNegativos as $h): ?>
                <div class="border rounded-3 p-3 bg-white">
                  <div class="fw-semibold"><?= nl2br(htmlspecialchars($h['fragmento'])) ?></div>
                  <small class="text-muted">
                    Tema: <?= htmlspecialchars($h['tema'] ?: 'Sin clasificar') ?>
                  </small>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>