<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

$pageTitle = 'Gracias';
require __DIR__ . '/includes/header_public.php';
?>

<div class="container mb-5">
  <div class="feedback-shell">
    <div class="card rounded-4 shadow-sm border-0 text-center">
      <div class="card-body p-5">
        <i class="bi bi-check-circle-fill text-success hero-icon"></i>
        <h4 class="mt-3 text-primary">Gracias por tu feedback</h4>
        <p class="text-muted mb-4">
          Tu opinión nos ayuda a mejorar continuamente nuestro servicio.
        </p>
        <a href="<?= APP_URL ?>/index.php" class="btn btn-outline-primary">
          <i class="bi bi-house me-2"></i>Ir al inicio
        </a>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>