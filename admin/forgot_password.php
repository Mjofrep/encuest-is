<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/security.php';

if (currentUser()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    if (!csrfValidate(is_string($csrf) ? $csrf : null)) {
        $error = 'Token CSRF invalido.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $ok = true;
        } else {
            $pdo = db();
            $stmt = $pdo->prepare("SELECT id, email FROM fb_usuarios WHERE email = ? AND estado = 1 LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expiresAt = (new DateTimeImmutable('+2 hours'))->format('Y-m-d H:i:s');

                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("UPDATE fb_password_resets SET used_at = NOW() WHERE usuario_id = ? AND used_at IS NULL");
                    $stmt->execute([(int)$user['id']]);

                    $stmt = $pdo->prepare("INSERT INTO fb_password_resets (usuario_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([(int)$user['id'], $tokenHash, $expiresAt]);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                }

                $baseUrl = getBaseUrl();
                $resetLink = $baseUrl . APP_URL . '/admin/reset_password.php?token=' . $token;

                $subject = 'Restablecer contrasena';
                $body = "Solicitaste restablecer tu contrasena.\n\n";
                $body .= "Ingresa al siguiente enlace para continuar:\n" . $resetLink . "\n\n";
                $body .= "Si no solicitaste esto, ignora este mensaje.";

                sendMail((string)$user['email'], $subject, $body);

                auditLog((int)$user['id'], 'password_reset_solicitado', 'Solicitud de reset');
            }

            $ok = true;
        }
    }
}

$pageTitle = 'Recuperar clave';
require __DIR__ . '/../includes/header_public.php';
?>

<div class="container mb-5">
  <div class="feedback-shell">
    <div class="card rounded-4">
      <div class="card-body p-4 p-md-5">
        <h4 class="text-primary mb-3">Recuperar clave</h4>

        <?php if ($ok): ?>
          <div class="alert alert-info">Si el correo existe, enviamos un enlace de recuperacion.</div>
        <?php elseif ($error !== ''): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" class="row g-3">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
          <div class="col-12">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="col-12 d-flex justify-content-between align-items-center">
            <button class="btn btn-primary">Enviar enlace</button>
            <a href="login.php" class="text-decoration-none">Volver al login</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
