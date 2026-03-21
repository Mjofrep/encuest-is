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
        <div class="text-muted">3 preguntas</div>
      </div>

      <div class="progress mb-4">
        <div class="progress-bar bg-success" style="width:100%"></div>
      </div>

      <form method="post" action="guardar_feedback.php" id="frmFeedback">
        <input type="hidden" name="campana_id" value="<?= (int)$campana['id'] ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <div class="mb-4">
          <label class="form-label fw-semibold">1. ¿Cómo calificarías tu experiencia?</label>
          <div class="d-flex gap-2 flex-wrap">
            <?php for ($i=1; $i<=5; $i++): ?>
              <input type="radio" class="btn-check" name="calificacion" id="cal_<?= $i ?>" value="<?= $i ?>" required>
              <label class="btn btn-outline-primary score-option" for="cal_<?= $i ?>"><?= $i ?></label>
            <?php endfor; ?>
          </div>
          <small class="text-muted d-block mt-2">1 = Muy mala / 5 = Excelente</small>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">2. ¿Qué fue lo mejor de tu experiencia?</label>
          <textarea name="respuesta_2" class="form-control" rows="3" maxlength="1000" required></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">3. ¿Qué deberíamos mejorar?</label>
          <textarea name="comentario" class="form-control" rows="3" maxlength="1000" required></textarea>
        </div>

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
          <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-send me-2"></i>Enviar feedback
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>