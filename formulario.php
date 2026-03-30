<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';

$pdo = db();
$token = trim($_GET['token'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM fb_campanas WHERE url_token = ? AND estado = 'activa' LIMIT 1");
$stmt->execute([$token]);
$campana = $stmt->fetch();

if (!$campana) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM fb_preguntas WHERE campana_id = ? ORDER BY orden ASC, id ASC");
$stmt->execute([(int)$campana['id']]);
$preguntas = $stmt->fetchAll();

$opcionesPorPregunta = [];
if ($preguntas) {
    $ids = array_map(static fn($p) => (int)$p['id'], $preguntas);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM fb_preguntas_opciones WHERE pregunta_id IN ($placeholders) ORDER BY orden ASC, id ASC");
    $stmt->execute($ids);
    $opciones = $stmt->fetchAll();
    foreach ($opciones as $opcion) {
        $pid = (int)$opcion['pregunta_id'];
        $opcionesPorPregunta[$pid][] = $opcion;
    }
}

$pageTitle = 'Formulario Feedback';
require __DIR__ . '/includes/header_public.php';
?>

<div class="container mb-5">
  <div class="feedback-shell">
    <div class="card rounded-4 p-3 p-md-4">
      <div class="mb-3">
        <h5 class="text-primary mb-1"><?= htmlspecialchars($campana['nombre']) ?></h5>
        <small class="text-muted">Tu opinión es importante. Completa este feedback breve.</small>
      </div>

      <div class="d-flex align-items-center justify-content-between mb-2">
        <div><strong>Formulario breve</strong></div>
        <div class="text-muted"><?= count($preguntas) ?> preguntas</div>
      </div>

      <div class="progress mb-4">
        <div class="progress-bar bg-success" style="width:100%"></div>
      </div>

      <form method="post" action="guardar_feedback.php" id="frmFeedback">
        <input type="hidden" name="campana_id" value="<?= (int)$campana['id'] ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <?php if (!$preguntas): ?>
          <div class="alert alert-warning">Esta campaña no tiene preguntas configuradas.</div>
        <?php else: ?>
          <?php foreach ($preguntas as $index => $pregunta): ?>
            <?php
              $numero = $index + 1;
              $tipo = (string)$pregunta['tipo'];
              $campo = 'pregunta_' . (int)$pregunta['id'];
              $obligatoria = (int)$pregunta['obligatoria'] === 1;
            ?>
            <div class="mb-4">
              <label class="form-label fw-semibold">
                <?= $numero ?>. <?= htmlspecialchars((string)$pregunta['texto_pregunta']) ?>
              </label>

              <?php if ($tipo === 'escala'): ?>
                <div class="d-flex gap-2 flex-wrap">
                  <?php for ($i=1; $i<=5; $i++): ?>
                    <input type="radio" class="btn-check" name="<?= htmlspecialchars($campo) ?>" id="cal_<?= (int)$pregunta['id'] ?>_<?= $i ?>" value="<?= $i ?>" <?= $obligatoria ? 'required' : '' ?>>
                    <label class="btn btn-outline-primary score-option" for="cal_<?= (int)$pregunta['id'] ?>_<?= $i ?>"><?= $i ?></label>
                  <?php endfor; ?>
                </div>
                <small class="text-muted d-block mt-2">1 = Muy mala / 5 = Excelente</small>

              <?php elseif ($tipo === 'texto'): ?>
                <textarea name="<?= htmlspecialchars($campo) ?>" class="form-control" rows="3" maxlength="1000" <?= $obligatoria ? 'required' : '' ?>></textarea>

              <?php elseif ($tipo === 'opcion'): ?>
                <select name="<?= htmlspecialchars($campo) ?>" class="form-select" <?= $obligatoria ? 'required' : '' ?>>
                  <option value="">Selecciona una opcion</option>
                  <?php foreach (($opcionesPorPregunta[(int)$pregunta['id']] ?? []) as $opcion): ?>
                    <option value="<?= (int)$opcion['id'] ?>"><?= htmlspecialchars((string)$opcion['texto_opcion']) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <!-- div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label">Sucursal / Área</label>
            <input type="text" name="sucursal" class="form-control" maxlength="100" placeholder="Opcional">
          </div>
          <div class="col-md-6">
            <label class="form-label">Canal</label>
            <input type="text" name="canal" class="form-control" maxlength="100" placeholder="Opcional">
          </div>
        </div-->

        <div class="d-flex justify-content-between">
          <a href="index.php?token=<?= urlencode($token) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Volver
          </a>
          <button type="submit" class="btn btn-primary px-4" <?= !$preguntas ? 'disabled' : '' ?>>
            <i class="bi bi-send me-2"></i>Enviar feedback
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
