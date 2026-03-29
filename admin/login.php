<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

if (currentUser()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$msg = trim($_GET['msg'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    if (!csrfValidate(is_string($csrf) ? $csrf : null)) {
        $error = 'Token CSRF invalido.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if (!loginUser($email, $password)) {
            $error = 'Credenciales incorrectas.';
        } else {
            header('Location: dashboard.php');
            exit;
        }
    }
}

$pageTitle = 'Acceso administrador';
require __DIR__ . '/../includes/header_public.php';
?>

<div class="container mb-5">
  <div class="feedback-shell">
    <div class="card rounded-4">
      <div class="card-body p-4 p-md-5">
        <h4 class="text-primary mb-3">Acceso administrador</h4>

        <?php if ($msg === 'reset_ok'): ?>
          <div class="alert alert-success">Tu clave fue actualizada. Inicia sesion.</div>
        <?php elseif ($msg === 'reset_sent'): ?>
          <div class="alert alert-info">Si el correo existe, enviamos un enlace de recuperacion.</div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" class="row g-3">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
          <div class="col-12">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="col-12">
            <label class="form-label">Contrasena</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <div class="col-12 d-flex justify-content-between align-items-center">
            <button class="btn btn-primary">Ingresar</button>
            <a href="forgot_password.php" class="text-decoration-none">Olvidaste tu clave?</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
