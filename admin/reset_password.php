<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

if (currentUser()) {
    header('Location: dashboard.php');
    exit;
}

$token = trim($_GET['token'] ?? '');
$error = '';
$ok = false;

function getResetRecord(string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $tokenHash = hash('sha256', $token);
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id, usuario_id, expires_at, used_at FROM fb_password_resets WHERE token_hash = ? LIMIT 1");
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    if (!empty($row['used_at'])) {
        return null;
    }

    $expiresAt = $row['expires_at'] ?? '';
    if ($expiresAt === '' || strtotime((string)$expiresAt) < time()) {
        return null;
    }

    $row['token_hash'] = $tokenHash;
    return $row;
}

$record = getResetRecord($token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    $tokenPost = trim($_POST['token'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');

    if (!csrfValidate(is_string($csrf) ? $csrf : null)) {
        $error = 'Token CSRF invalido.';
    } elseif ($password === '' || strlen($password) < 8) {
        $error = 'La contrasena debe tener al menos 8 caracteres.';
    } elseif ($password !== $password2) {
        $error = 'Las contrasenas no coinciden.';
    } else {
        $record = getResetRecord($tokenPost);
        if (!$record) {
            $error = 'El enlace no es valido o expirado.';
        } else {
            $pdo = db();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE fb_usuarios SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), (int)$record['usuario_id']]);

            $stmt = $pdo->prepare("UPDATE fb_password_resets SET used_at = NOW() WHERE id = ?");
            $stmt->execute([(int)$record['id']]);

            $pdo->commit();

            auditLog((int)$record['usuario_id'], 'password_reset', 'Reset completado');

            header('Location: login.php?msg=reset_ok');
            exit;
        }
    }
}

$pageTitle = 'Restablecer clave';
require __DIR__ . '/../includes/header_public.php';
?>

<div class="container mb-5">
  <div class="feedback-shell">
    <div class="card rounded-4">
      <div class="card-body p-4 p-md-5">
        <h4 class="text-primary mb-3">Restablecer clave</h4>

        <?php if ($record === null && $error === ''): ?>
          <div class="alert alert-danger">El enlace no es valido o expirado.</div>
        <?php elseif ($error !== ''): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($record !== null): ?>
          <form method="post" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <div class="col-12">
              <label class="form-label">Nueva contrasena</label>
              <input type="password" name="password" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">Confirmar contrasena</label>
              <input type="password" name="password2" class="form-control" required>
            </div>
            <div class="col-12 d-flex justify-content-between align-items-center">
              <button class="btn btn-primary">Actualizar</button>
              <a href="login.php" class="text-decoration-none">Volver al login</a>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
