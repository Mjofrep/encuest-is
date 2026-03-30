<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';

enforceHttpsIfNeeded();
sendSecurityHeaders();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <?php require __DIR__ . '/estilos_feedback.php'; ?>
</head>
<body>

<header class="topbar py-3 mb-4">
  <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
      <img src="<?= APP_LOGO ?>" alt="Logo" style="height:50px;" onerror="this.style.display='none'">
      <div>
        <strong><?= APP_NAME ?></strong><br>
        <small class="text-muted"><?= APP_SUBTITLE ?></small>
      </div>
    </div>

    <div class="d-flex gap-2 flex-wrap align-items-center">
      <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-outline-primary btn-sm">Dashboard</a>
      <a href="<?= APP_URL ?>/admin/campanas.php" class="btn btn-outline-primary btn-sm">Campanas</a>
      <?php if (function_exists('userHasRole') && userHasRole('admin')): ?>
        <a href="<?= APP_URL ?>/admin/preguntas.php" class="btn btn-outline-primary btn-sm">Preguntas</a>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/admin/respuestas.php" class="btn btn-outline-primary btn-sm">Respuestas</a>
      <?php if (function_exists('userHasRole') && userHasRole('admin')): ?>
        <a href="<?= APP_URL ?>/admin/usuarios.php" class="btn btn-outline-primary btn-sm">Usuarios</a>
      <?php endif; ?>
    </div>

    <?php if (function_exists('currentUser')): ?>
      <?php $user = currentUser(); ?>
      <?php if ($user): ?>
        <div class="d-flex gap-2 align-items-center">
          <small class="text-muted">Hola, <?= htmlspecialchars((string)$user['nombre']) ?></small>
          <a href="<?= APP_URL ?>/admin/logout.php" class="btn btn-outline-secondary btn-sm">Salir</a>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</header>
