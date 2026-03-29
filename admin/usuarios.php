<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireRole(['admin']);

$pdo = db();

$rolesDisponibles = $pdo->query("SELECT id, clave, nombre FROM fb_roles ORDER BY id ASC")->fetchAll();
$rolesMap = [];
foreach ($rolesDisponibles as $r) {
    $rolesMap[$r['id']] = $r['clave'];
}

function filtrarRolesSeleccionados(array $rolesIds, array $rolesDisponibles): array
{
    $valid = [];
    $idsDisponibles = array_column($rolesDisponibles, 'id');
    foreach ($rolesIds as $id) {
        $id = (int)$id;
        if (in_array($id, $idsDisponibles, true)) {
            $valid[] = $id;
        }
    }
    return array_values(array_unique($valid));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    if (!csrfValidate(is_string($csrf) ? $csrf : null)) {
        header('Location: usuarios.php?msg=csrf');
        exit;
    }

    $action = trim($_POST['action'] ?? '');
    $current = currentUser();

    if ($action === 'create') {
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $rolesIds = filtrarRolesSeleccionados((array)($_POST['roles'] ?? []), $rolesDisponibles);

        if ($nombre === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: usuarios.php?msg=invalid');
            exit;
        }

        if (strlen($password) < 8) {
            header('Location: usuarios.php?msg=weak');
            exit;
        }

        if (empty($rolesIds)) {
            header('Location: usuarios.php?msg=roles');
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM fb_usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            header('Location: usuarios.php?msg=exists');
            exit;
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO fb_usuarios (nombre, email, password_hash, estado, created_at, updated_at) VALUES (?, ?, ?, 1, NOW(), NOW())");
        $stmt->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $userId = (int)$pdo->lastInsertId();

        $stmtRole = $pdo->prepare("INSERT INTO fb_usuarios_roles (usuario_id, rol_id) VALUES (?, ?)");
        foreach ($rolesIds as $roleId) {
            $stmtRole->execute([$userId, $roleId]);
        }

        $pdo->commit();

        if ($current) {
            auditLog((int)$current['id'], 'usuario_creado', 'usuario_id=' . $userId);
        }

        header('Location: usuarios.php?msg=created');
        exit;
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $rolesIds = filtrarRolesSeleccionados((array)($_POST['roles'] ?? []), $rolesDisponibles);

        if ($id <= 0 || $nombre === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: usuarios.php?msg=invalid');
            exit;
        }

        if (!empty($password) && strlen($password) < 8) {
            header('Location: usuarios.php?msg=weak');
            exit;
        }

        if (empty($rolesIds)) {
            header('Location: usuarios.php?msg=roles');
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM fb_usuarios WHERE email = ? AND id <> ? LIMIT 1");
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            header('Location: usuarios.php?msg=exists');
            exit;
        }

        $pdo->beginTransaction();

        if ($password !== '') {
            $stmt = $pdo->prepare("UPDATE fb_usuarios SET nombre = ?, email = ?, password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT), $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE fb_usuarios SET nombre = ?, email = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$nombre, $email, $id]);
        }

        $stmt = $pdo->prepare("DELETE FROM fb_usuarios_roles WHERE usuario_id = ?");
        $stmt->execute([$id]);

        $stmtRole = $pdo->prepare("INSERT INTO fb_usuarios_roles (usuario_id, rol_id) VALUES (?, ?)");
        foreach ($rolesIds as $roleId) {
            $stmtRole->execute([$id, $roleId]);
        }

        $pdo->commit();

        if ($current) {
            auditLog((int)$current['id'], 'usuario_actualizado', 'usuario_id=' . $id);
        }

        header('Location: usuarios.php?msg=updated');
        exit;
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $estado = (int)($_POST['estado'] ?? 0);

        if ($id <= 0) {
            header('Location: usuarios.php?msg=invalid');
            exit;
        }

        if ($current && (int)$current['id'] === $id) {
            header('Location: usuarios.php?msg=self');
            exit;
        }

        $stmt = $pdo->prepare("UPDATE fb_usuarios SET estado = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$estado, $id]);

        if ($current) {
            auditLog((int)$current['id'], 'usuario_estado', 'usuario_id=' . $id . ' estado=' . $estado);
        }

        header('Location: usuarios.php?msg=updated');
        exit;
    }
}

$editId = (int)($_GET['edit_id'] ?? 0);
$usuarioEdit = null;
$rolesEdit = [];
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT id, nombre, email, estado FROM fb_usuarios WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $usuarioEdit = $stmt->fetch();

    if ($usuarioEdit) {
        $stmt = $pdo->prepare("SELECT rol_id FROM fb_usuarios_roles WHERE usuario_id = ?");
        $stmt->execute([$editId]);
        $rolesEdit = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}

$usuarios = $pdo->query("SELECT id, nombre, email, estado, created_at FROM fb_usuarios ORDER BY created_at DESC")->fetchAll();

function cargarRolesUsuario(int $userId, array $rolesMap): array
{
    $pdo = db();
    $stmt = $pdo->prepare("SELECT rol_id FROM fb_usuarios_roles WHERE usuario_id = ?");
    $stmt->execute([$userId]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $labels = [];
    foreach ($ids as $id) {
        if (isset($rolesMap[$id])) {
            $labels[] = $rolesMap[$id];
        }
    }
    return $labels;
}

$pageTitle = 'Usuarios';
require __DIR__ . '/../includes/header_admin.php';
?>

<div class="container mb-5">
  <?php if (!empty($_GET['msg'])): ?>
    <?php
      $msg = $_GET['msg'];
      $alert = 'info';
      $text = 'Operacion realizada.';
      if ($msg === 'created') { $alert = 'success'; $text = 'Usuario creado.'; }
      elseif ($msg === 'updated') { $alert = 'success'; $text = 'Usuario actualizado.'; }
      elseif ($msg === 'exists') { $alert = 'warning'; $text = 'Email ya existe.'; }
      elseif ($msg === 'weak') { $alert = 'warning'; $text = 'Contrasena muy corta.'; }
      elseif ($msg === 'roles') { $alert = 'warning'; $text = 'Debe asignar al menos un rol.'; }
      elseif ($msg === 'invalid') { $alert = 'danger'; $text = 'Datos invalidos.'; }
      elseif ($msg === 'csrf') { $alert = 'danger'; $text = 'Token CSRF invalido.'; }
      elseif ($msg === 'self') { $alert = 'warning'; $text = 'No puedes desactivar tu propio usuario.'; }
    ?>
    <div class="alert alert-<?= $alert ?>"><?= htmlspecialchars($text) ?></div>
  <?php endif; ?>

  <div class="card rounded-4 mb-4">
    <div class="card-body">
      <h5 class="section-title mb-3"><?= $usuarioEdit ? 'Editar usuario' : 'Nuevo usuario' ?></h5>

      <form method="post" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="<?= $usuarioEdit ? 'update' : 'create' ?>">
        <?php if ($usuarioEdit): ?>
          <input type="hidden" name="id" value="<?= (int)$usuarioEdit['id'] ?>">
        <?php endif; ?>

        <div class="col-md-4">
          <label class="form-label">Nombre</label>
          <input type="text" name="nombre" class="form-control" required value="<?= htmlspecialchars((string)($usuarioEdit['nombre'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars((string)($usuarioEdit['email'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Contrasena <?= $usuarioEdit ? '(opcional)' : '' ?></label>
          <input type="password" name="password" class="form-control" <?= $usuarioEdit ? '' : 'required' ?>>
        </div>

        <div class="col-12">
          <label class="form-label">Roles</label>
          <div class="d-flex flex-wrap gap-3">
            <?php foreach ($rolesDisponibles as $r): ?>
              <?php
                $checked = in_array((int)$r['id'], $rolesEdit, true);
              ?>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="roles[]" value="<?= (int)$r['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                <label class="form-check-label"><?= htmlspecialchars($r['clave']) ?></label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="col-12">
          <button class="btn btn-primary"><?= $usuarioEdit ? 'Guardar cambios' : 'Crear usuario' ?></button>
          <?php if ($usuarioEdit): ?>
            <a href="usuarios.php" class="btn btn-outline-secondary ms-2">Cancelar</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <div class="card rounded-4">
    <div class="card-body">
      <h5 class="section-title mb-3">Listado de usuarios</h5>

      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Email</th>
              <th>Roles</th>
              <th>Estado</th>
              <th>Creado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$usuarios): ?>
              <tr><td colspan="6" class="text-center text-muted">No hay usuarios.</td></tr>
            <?php else: ?>
              <?php foreach ($usuarios as $u): ?>
                <?php
                  $rolesUser = cargarRolesUsuario((int)$u['id'], $rolesMap);
                  $activo = (int)$u['estado'] === 1;
                ?>
                <tr>
                  <td><?= htmlspecialchars($u['nombre']) ?></td>
                  <td><?= htmlspecialchars($u['email']) ?></td>
                  <td>
                    <?php if ($rolesUser): ?>
                      <?php foreach ($rolesUser as $role): ?>
                        <span class="badge badge-soft-secondary me-1"><?= htmlspecialchars($role) ?></span>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <span class="text-muted">Sin roles</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($activo): ?>
                      <span class="badge badge-soft-success">Activo</span>
                    <?php else: ?>
                      <span class="badge badge-soft-secondary">Inactivo</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($u['created_at']) ?></td>
                  <td>
                    <div class="d-flex gap-1 flex-wrap">
                      <a href="usuarios.php?edit_id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                        <i class="bi bi-pencil"></i>
                      </a>

                      <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="estado" value="<?= $activo ? 0 : 1 ?>">
                        <button class="btn btn-sm <?= $activo ? 'btn-outline-warning' : 'btn-outline-success' ?>" title="<?= $activo ? 'Desactivar' : 'Activar' ?>">
                          <i class="bi <?= $activo ? 'bi-person-x' : 'bi-person-check' ?>"></i>
                        </button>
                      </form>
                    </div>
                  </td>
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
