<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireRole(['admin']);

$fatalError = '';
$warningMsg = '';
$campanas = [];
$pdo = null;

try {
    $pdo = db();
} catch (Throwable $e) {
    $fatalError = 'No se pudo conectar a la base de datos. Revisa config/secrets.php.';
    error_log('Error DB preguntas: ' . $e->getMessage());
}

$campanaId = (int)($_GET['campana_id'] ?? 0);

if ($pdo) {
    try {
        $campanas = $pdo->query("SELECT id, nombre FROM fb_campanas ORDER BY nombre")->fetchAll();
    } catch (Throwable $e) {
        $fatalError = 'No se pudo cargar campañas.';
        error_log('Error campañas preguntas: ' . $e->getMessage());
    }
}

function parseOpciones(string $raw): array
{
    $lines = preg_split('/\r\n|\r|\n/', $raw);
    $clean = [];
    foreach ($lines as $line) {
        $text = trim($line);
        if ($text !== '') {
            $clean[] = $text;
        }
    }
    return array_values(array_unique($clean));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    try {
        $csrf = $_POST['csrf_token'] ?? null;
        if (!csrfValidate(is_string($csrf) ? $csrf : null)) {
            header('Location: preguntas.php?msg=csrf');
            exit;
        }

        $action = trim($_POST['action'] ?? '');
        $current = currentUser();

        if ($action === 'create' || $action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $campanaIdPost = (int)($_POST['campana_id'] ?? 0);
            $texto = trim($_POST['texto_pregunta'] ?? '');
            $tipo = trim($_POST['tipo'] ?? '');
            $orden = (int)($_POST['orden'] ?? 1);
            $obligatoria = isset($_POST['obligatoria']) ? 1 : 0;
            $opcionesRaw = (string)($_POST['opciones'] ?? '');
            $opciones = $tipo === 'opcion' ? parseOpciones($opcionesRaw) : [];

            if ($campanaIdPost <= 0 || $texto === '' || !in_array($tipo, ['escala', 'texto', 'opcion'], true)) {
                header('Location: preguntas.php?msg=invalid');
                exit;
            }

            if ($tipo === 'opcion' && empty($opciones)) {
                header('Location: preguntas.php?msg=opciones');
                exit;
            }

            $pdo->beginTransaction();

            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO fb_preguntas (campana_id, texto_pregunta, tipo, orden, obligatoria, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$campanaIdPost, $texto, $tipo, $orden, $obligatoria]);
                $preguntaId = (int)$pdo->lastInsertId();

                if ($tipo === 'opcion') {
                    $stmtOpt = $pdo->prepare("INSERT INTO fb_preguntas_opciones (pregunta_id, texto_opcion, orden) VALUES (?, ?, ?)");
                    $pos = 1;
                    foreach ($opciones as $opcion) {
                        $stmtOpt->execute([$preguntaId, $opcion, $pos]);
                        $pos++;
                    }
                }

                $pdo->commit();

                if ($current) {
                    auditLog((int)$current['id'], 'pregunta_creada', 'pregunta_id=' . $preguntaId);
                }

                header('Location: preguntas.php?campana_id=' . $campanaIdPost . '&msg=created');
                exit;
            }

            if ($id <= 0) {
                $pdo->rollBack();
                header('Location: preguntas.php?msg=invalid');
                exit;
            }

            $stmt = $pdo->prepare("UPDATE fb_preguntas SET campana_id = ?, texto_pregunta = ?, tipo = ?, orden = ?, obligatoria = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$campanaIdPost, $texto, $tipo, $orden, $obligatoria, $id]);

            $stmt = $pdo->prepare("DELETE FROM fb_preguntas_opciones WHERE pregunta_id = ?");
            $stmt->execute([$id]);

            if ($tipo === 'opcion') {
                $stmtOpt = $pdo->prepare("INSERT INTO fb_preguntas_opciones (pregunta_id, texto_opcion, orden) VALUES (?, ?, ?)");
                $pos = 1;
                foreach ($opciones as $opcion) {
                    $stmtOpt->execute([$id, $opcion, $pos]);
                    $pos++;
                }
            }

            $pdo->commit();

            if ($current) {
                auditLog((int)$current['id'], 'pregunta_actualizada', 'pregunta_id=' . $id);
            }

            header('Location: preguntas.php?campana_id=' . $campanaIdPost . '&msg=updated');
            exit;
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                header('Location: preguntas.php?msg=invalid');
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM fb_preguntas WHERE id = ?");
            $stmt->execute([$id]);

            if ($current) {
                auditLog((int)$current['id'], 'pregunta_eliminada', 'pregunta_id=' . $id);
            }

            header('Location: preguntas.php?msg=deleted');
            exit;
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $fatalError = 'No se pudo guardar la pregunta. Verifica que existan las tablas de preguntas.';
        error_log('Error preguntas admin: ' . $e->getMessage());
    }
}

$editId = (int)($_GET['edit_id'] ?? 0);
$preguntaEdit = null;
$opcionesEdit = [];
$preguntas = [];
$opcionesMap = [];

if ($pdo && $fatalError === '') {
    try {
        if ($editId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM fb_preguntas WHERE id = ? LIMIT 1");
            $stmt->execute([$editId]);
            $preguntaEdit = $stmt->fetch();

            if ($preguntaEdit) {
                $stmt = $pdo->prepare("SELECT texto_opcion FROM fb_preguntas_opciones WHERE pregunta_id = ? ORDER BY orden ASC, id ASC");
                $stmt->execute([$editId]);
                $opcionesEdit = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            }
        }

        $where = [];
        $params = [];
        if ($campanaId > 0) {
            $where[] = 'p.campana_id = ?';
            $params[] = $campanaId;
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "
            SELECT p.*, c.nombre AS campana
            FROM fb_preguntas p
            JOIN fb_campanas c ON c.id = p.campana_id
            $whereSql
            ORDER BY c.nombre ASC, p.orden ASC, p.id ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $preguntas = $stmt->fetchAll();

        if ($preguntas) {
            $ids = array_map(static fn($p) => (int)$p['id'], $preguntas);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            try {
                $stmt = $pdo->prepare("SELECT * FROM fb_preguntas_opciones WHERE pregunta_id IN ($placeholders) ORDER BY orden ASC, id ASC");
                $stmt->execute($ids);
                $opciones = $stmt->fetchAll();
                foreach ($opciones as $opcion) {
                    $pid = (int)$opcion['pregunta_id'];
                    $opcionesMap[$pid][] = $opcion['texto_opcion'];
                }
            } catch (Throwable $e) {
                $warningMsg = 'No se pudieron cargar opciones. Verifica la tabla fb_preguntas_opciones.';
                error_log('Error opciones preguntas: ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        $fatalError = 'No se pudo cargar preguntas. Verifica que existan las tablas fb_preguntas.';
        error_log('Error carga preguntas: ' . $e->getMessage());
    }
}

$pageTitle = 'Preguntas';
require __DIR__ . '/../includes/header_admin.php';
?>

<div class="container mb-5">
  <?php if ($fatalError !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($fatalError) ?></div>
  <?php endif; ?>

  <?php if ($warningMsg !== ''): ?>
    <div class="alert alert-warning"><?= htmlspecialchars($warningMsg) ?></div>
  <?php endif; ?>

  <?php if (!empty($_GET['msg'])): ?>
    <?php
      $msg = $_GET['msg'];
      $alert = 'info';
      $text = 'Operacion realizada.';
      if ($msg === 'created') { $alert = 'success'; $text = 'Pregunta creada.'; }
      elseif ($msg === 'updated') { $alert = 'success'; $text = 'Pregunta actualizada.'; }
      elseif ($msg === 'deleted') { $alert = 'warning'; $text = 'Pregunta eliminada.'; }
      elseif ($msg === 'invalid') { $alert = 'danger'; $text = 'Datos invalidos.'; }
      elseif ($msg === 'opciones') { $alert = 'warning'; $text = 'Debes ingresar opciones para este tipo.'; }
      elseif ($msg === 'csrf') { $alert = 'danger'; $text = 'Token CSRF invalido.'; }
    ?>
    <div class="alert alert-<?= $alert ?>"><?= htmlspecialchars($text) ?></div>
  <?php endif; ?>

  <div class="card rounded-4 mb-4">
    <div class="card-body">
      <h5 class="section-title mb-3"><?= $preguntaEdit ? 'Editar pregunta' : 'Nueva pregunta' ?></h5>

      <form method="post" class="row g-3" id="formPregunta" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="<?= $preguntaEdit ? 'update' : 'create' ?>">
        <?php if ($preguntaEdit): ?>
          <input type="hidden" name="id" value="<?= (int)$preguntaEdit['id'] ?>">
        <?php endif; ?>

        <div class="col-md-4">
          <label class="form-label">Campaña</label>
          <select name="campana_id" class="form-select" required>
            <option value="">Selecciona</option>
            <?php foreach ($campanas as $c): ?>
              <?php $selected = (int)($preguntaEdit['campana_id'] ?? $campanaId) === (int)$c['id']; ?>
              <option value="<?= (int)$c['id'] ?>" <?= $selected ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-8">
          <label class="form-label">Texto de la pregunta</label>
          <input type="text" name="texto_pregunta" class="form-control" required value="<?= htmlspecialchars((string)($preguntaEdit['texto_pregunta'] ?? '')) ?>" autocomplete="off">
        </div>

        <div class="col-md-3">
          <label class="form-label">Tipo</label>
          <?php $tipoSel = (string)($preguntaEdit['tipo'] ?? 'texto'); ?>
          <select name="tipo" class="form-select" required>
            <option value="escala" <?= $tipoSel === 'escala' ? 'selected' : '' ?>>Escala</option>
            <option value="texto" <?= $tipoSel === 'texto' ? 'selected' : '' ?>>Texto</option>
            <option value="opcion" <?= $tipoSel === 'opcion' ? 'selected' : '' ?>>Opcion</option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Orden</label>
          <input type="number" name="orden" class="form-control" min="1" value="<?= (int)($preguntaEdit['orden'] ?? 1) ?>" autocomplete="off">
        </div>

        <div class="col-md-2 d-flex align-items-end">
          <div class="form-check">
            <?php $obligatoriaSel = (int)($preguntaEdit['obligatoria'] ?? 1) === 1; ?>
            <input class="form-check-input" type="checkbox" name="obligatoria" <?= $obligatoriaSel ? 'checked' : '' ?>>
            <label class="form-check-label">Obligatoria</label>
          </div>
        </div>

        <div class="col-md-12">
          <label class="form-label">Opciones (solo tipo opcion, una por linea)</label>
          <textarea name="opciones" class="form-control" rows="3" autocomplete="off"><?= htmlspecialchars(implode("\n", $opcionesEdit)) ?></textarea>
        </div>

        <div class="col-12">
          <button class="btn btn-primary"><?= $preguntaEdit ? 'Guardar cambios' : 'Crear pregunta' ?></button>
          <?php if ($preguntaEdit): ?>
            <a href="preguntas.php" class="btn btn-outline-secondary ms-2">Cancelar</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <div class="card rounded-4">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="section-title mb-0">Listado de preguntas</h5>
        <form method="get" class="d-flex gap-2">
          <select name="campana_id" class="form-select">
            <option value="0">Todas las campañas</option>
            <?php foreach ($campanas as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $campanaId === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-outline-primary">Filtrar</button>
        </form>
      </div>

      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Campaña</th>
              <th>Pregunta</th>
              <th>Tipo</th>
              <th>Orden</th>
              <th>Obligatoria</th>
              <th>Opciones</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$preguntas): ?>
              <tr><td colspan="7" class="text-center text-muted">No hay preguntas.</td></tr>
            <?php else: ?>
              <?php foreach ($preguntas as $p): ?>
                <?php $ops = $opcionesMap[(int)$p['id']] ?? []; ?>
                <tr>
                  <td><?= htmlspecialchars($p['campana']) ?></td>
                  <td><?= htmlspecialchars($p['texto_pregunta']) ?></td>
                  <td><?= htmlspecialchars($p['tipo']) ?></td>
                  <td><?= (int)$p['orden'] ?></td>
                  <td><?= (int)$p['obligatoria'] === 1 ? 'Si' : 'No' ?></td>
                  <td>
                    <?php if ($ops): ?>
                      <?= htmlspecialchars(implode(', ', $ops)) ?>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="d-flex gap-1 flex-wrap">
                      <a href="preguntas.php?edit_id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                        <i class="bi bi-pencil"></i>
                      </a>
                      <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar pregunta?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" title="Eliminar">
                          <i class="bi bi-trash"></i>
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

<?php if (!$preguntaEdit && ($msg ?? '') === 'created'): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('formPregunta');
  if (!form) return;
  const text = form.querySelector('input[name="texto_pregunta"]');
  const tipo = form.querySelector('select[name="tipo"]');
  const orden = form.querySelector('input[name="orden"]');
  const obligatoria = form.querySelector('input[name="obligatoria"]');
  const opciones = form.querySelector('textarea[name="opciones"]');

  if (text) text.value = '';
  if (tipo) tipo.value = 'texto';
  if (orden) orden.value = '1';
  if (obligatoria) obligatoria.checked = true;
  if (opciones) opciones.value = '';
});
</script>
<?php endif; ?>
