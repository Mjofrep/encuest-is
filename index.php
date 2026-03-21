<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';

$pdo = db();
$token = trim($_GET['token'] ?? '');

$pageTitle = 'Inicio Feedback';
require __DIR__ . '/includes/header_public.php';

$campana = null;
if ($token !== '') {
    $stmt = $pdo->prepare("SELECT * FROM fb_campanas WHERE url_token = ? AND estado = 'activa' LIMIT 1");
    $stmt->execute([$token]);
    $campana = $stmt->fetch();
}
?>

<div class="container mb-5">
  <div class="feedback-shell">
    <div class="card rounded-4">
      <div class="card-body p-4 p-md-5">

        <?php if (!$campana): ?>
          <div class="text-center">
            <i class="bi bi-qr-code-scan text-danger hero-icon"></i>
            <h4 class="mt-3 text-danger">Campaña no disponible</h4>
            <p class="text-muted mb-0">
              El código QR no corresponde a una campaña activa o el enlace no es válido.
            </p>
          </div>
        <?php else: ?>
          <div class="text-center mb-4">
            <i class="bi bi-chat-square-heart text-primary hero-icon"></i>
            <h4 class="mt-3 text-primary"><?= htmlspecialchars($campana['nombre']) ?></h4>
            <p class="text-muted mb-2">
              <?= htmlspecialchars($campana['descripcion'] ?? 'Tu opinión nos ayuda a mejorar.') ?>
            </p>
            <small class="text-muted">Responde 3 preguntas rápidas. Te tomará menos de 1 minuto.</small>
          </div>

          <div class="soft-box mb-4">
            <div class="row text-center g-3">
              <div class="col-md-4">
                <i class="bi bi-lightning-charge text-primary fs-4"></i>
                <div class="fw-semibold mt-2">Rápido</div>
                <small class="text-muted">Solo 3 preguntas</small>
              </div>
              <div class="col-md-4">
                <i class="bi bi-phone text-primary fs-4"></i>
                <div class="fw-semibold mt-2">Mobile friendly</div>
                <small class="text-muted">Optimizado para celular</small>
              </div>
              <div class="col-md-4">
                <i class="bi bi-graph-up-arrow text-primary fs-4"></i>
                <div class="fw-semibold mt-2">Mejora continua</div>
                <small class="text-muted">Tu opinión genera métricas</small>
              </div>
            </div>
          </div>

          <div class="text-center">
            <a href="formulario.php?token=<?= urlencode($token) ?>" class="btn btn-primary px-4">
              <i class="bi bi-play-circle me-2"></i>Comenzar feedback
            </a>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>