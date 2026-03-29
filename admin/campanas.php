<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireRole(['admin']);

$pdo = db();

function generarToken(int $len = 24): string {
    return bin2hex(random_bytes((int)($len / 2)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    if (!csrfValidate(is_string($csrf) ? $csrf : null)) {
        http_response_code(400);
        echo 'Token CSRF invalido';
        exit;
    }

    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $estado = ($_POST['estado'] ?? 'activa') === 'inactiva' ? 'inactiva' : 'activa';

    if ($nombre !== '') {
        $token = generarToken();
        $stmt = $pdo->prepare("
            INSERT INTO fb_campanas (nombre, descripcion, url_token, estado, fecha_creacion)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$nombre, $descripcion !== '' ? $descripcion : null, $token, $estado]);
        $user = currentUser();
        if ($user) {
            auditLog((int)$user['id'], 'campana_creada', 'Campana: ' . $nombre);
        }

        header('Location: campanas.php?ok=1');
        exit;
    }
}

$campanas = $pdo->query("SELECT * FROM fb_campanas ORDER BY fecha_creacion DESC")->fetchAll();

$pageTitle = 'Campañas';
require __DIR__ . '/../includes/header_admin.php';
?>

<div class="container mb-5">
  <div class="card rounded-4 mb-4">
    <div class="card-body">
      <h5 class="section-title mb-3">Nueva campaña</h5>

      <form method="post" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <div class="col-md-4">
          <label class="form-label">Nombre</label>
          <input type="text" name="nombre" class="form-control" required>
        </div>
        <div class="col-md-5">
          <label class="form-label">Descripción</label>
          <input type="text" name="descripcion" class="form-control">
        </div>
        <div class="col-md-2">
          <label class="form-label">Estado</label>
          <select name="estado" class="form-select">
            <option value="activa">Activa</option>
            <option value="inactiva">Inactiva</option>
          </select>
        </div>
        <div class="col-md-1 align-self-end">
          <button class="btn btn-primary w-100">
            <i class="bi bi-plus-lg"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="card rounded-4">
    <div class="card-body">
      <h5 class="section-title mb-3">Listado de campañas</h5>

      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Descripción</th>
              <th>Token</th>
              <th>Estado</th>
              <th>URL</th>
              <th>QR</th>
              <th>Fecha</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$campanas): ?>
              <tr><td colspan="7" class="text-center text-muted">No hay campañas.</td></tr>
            <?php else: ?>
              <?php foreach ($campanas as $c): ?>
                <?php
                  $urlCampana = 'https://www.noetica.cl/feedback/index.php?token=' . $c['url_token'];
                ?>
                <tr>
                  <td><?= htmlspecialchars($c['nombre']) ?></td>
                  <td><?= htmlspecialchars($c['descripcion'] ?? '') ?></td>
                  <td><code><?= htmlspecialchars($c['url_token']) ?></code></td>
                  <td>
                    <?php if ($c['estado'] === 'activa'): ?>
                      <span class="badge badge-soft-success">Activa</span>
                    <?php else: ?>
                      <span class="badge badge-soft-secondary">Inactiva</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <input
                      type="text"
                      class="form-control form-control-sm"
                      value="<?= htmlspecialchars($urlCampana) ?>"
                      readonly
                    >
                  </td>
                  <td>
                    <a href="qr_campana.php?token=<?= urlencode($c['url_token']) ?>"
                       class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-qr-code me-1"></i>Generar QR
                    </a>
                  </td>
                  <td><?= htmlspecialchars($c['fecha_creacion']) ?></td>
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
