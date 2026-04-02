<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(['admin', 'analista', 'lector']);

$pdo = db();

$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
$campanaId = (int)($_GET['campana_id'] ?? 0);

$where = [];
$params = [];

if ($desde !== '') {
    $where[] = "DATE(r.fecha_respuesta) >= ?";
    $params[] = $desde;
}
if ($hasta !== '') {
    $where[] = "DATE(r.fecha_respuesta) <= ?";
    $params[] = $hasta;
}
if ($campanaId > 0) {
    $where[] = "r.campana_id = ?";
    $params[] = $campanaId;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$campanas = $pdo->query("SELECT id, nombre FROM fb_campanas ORDER BY nombre")->fetchAll();

$sqlKpi = "
SELECT 
    COUNT(*) AS total_respuestas,
    ROUND(AVG(r.calificacion),2) AS promedio_calificacion
FROM fb_respuestas r
$whereSql
";
$stmt = $pdo->prepare($sqlKpi);
$stmt->execute($params);
$kpi = $stmt->fetch() ?: ['total_respuestas' => 0, 'promedio_calificacion' => 0];

$sqlSent = "
SELECT 
    COALESCE(a.sentimiento, 'sin_analisis') AS sentimiento,
    COUNT(*) AS total
FROM fb_respuestas r
LEFT JOIN fb_analisis_ia a ON a.respuesta_id = r.id
$whereSql
GROUP BY COALESCE(a.sentimiento, 'sin_analisis')
ORDER BY total DESC
";
$stmt = $pdo->prepare($sqlSent);
$stmt->execute($params);
$sentimientos = $stmt->fetchAll();

$sqlTema = "
SELECT 
    COALESCE(a.tema_principal, 'Sin clasificar') AS tema,
    COUNT(*) AS total
FROM fb_respuestas r
LEFT JOIN fb_analisis_ia a ON a.respuesta_id = r.id
$whereSql
GROUP BY COALESCE(a.tema_principal, 'Sin clasificar')
ORDER BY total DESC
LIMIT 5
";
$stmt = $pdo->prepare($sqlTema);
$stmt->execute($params);
$temas = $stmt->fetchAll();

$sqlFragmentos = "
SELECT 
    d.tipo,
    COUNT(*) AS total
FROM fb_analisis_ia_detalle d
JOIN fb_respuestas r ON r.id = d.respuesta_id
$whereSql
GROUP BY d.tipo
ORDER BY d.tipo
";
$stmt = $pdo->prepare($sqlFragmentos);
$stmt->execute($params);
$fragmentos = $stmt->fetchAll();

$sqlTopPositivos = "
SELECT 
    COALESCE(NULLIF(TRIM(d.tema), ''), 'Sin clasificar') AS tema,
    COUNT(*) AS total
FROM fb_analisis_ia_detalle d
JOIN fb_respuestas r ON r.id = d.respuesta_id
$whereSql
" . ($whereSql ? " AND d.tipo = 'positivo'" : "WHERE d.tipo = 'positivo'") . "
GROUP BY COALESCE(NULLIF(TRIM(d.tema), ''), 'Sin clasificar')
ORDER BY total DESC, tema ASC
LIMIT 5
";
$stmt = $pdo->prepare($sqlTopPositivos);
$stmt->execute($params);
$topPositivos = $stmt->fetchAll();

$sqlTopNegativos = "
SELECT 
    COALESCE(NULLIF(TRIM(d.tema), ''), 'Sin clasificar') AS tema,
    COUNT(*) AS total
FROM fb_analisis_ia_detalle d
JOIN fb_respuestas r ON r.id = d.respuesta_id
$whereSql
" . ($whereSql ? " AND d.tipo = 'negativo'" : "WHERE d.tipo = 'negativo'") . "
GROUP BY COALESCE(NULLIF(TRIM(d.tema), ''), 'Sin clasificar')
ORDER BY total DESC, tema ASC
LIMIT 5
";
$stmt = $pdo->prepare($sqlTopNegativos);
$stmt->execute($params);
$topNegativos = $stmt->fetchAll();
$sqlUltimas = "
SELECT r.id, r.fecha_respuesta, c.nombre AS campana, r.calificacion, r.comentario,
       a.sentimiento, a.tema_principal
FROM fb_respuestas r
JOIN fb_campanas c ON c.id = r.campana_id
LEFT JOIN fb_analisis_ia a ON a.respuesta_id = r.id
$whereSql
ORDER BY r.fecha_respuesta DESC
LIMIT 10
";
$stmt = $pdo->prepare($sqlUltimas);
$stmt->execute($params);
$ultimas = $stmt->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/header_admin.php';

function normalizarTexto(string $texto): string
{
    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9áéíóúñü]+/iu', ' ', $texto) ?? '';
    return trim($texto);
}

function obtenerStopwords(): array
{
    return [
        'de','la','que','el','en','y','a','los','del','se','las','por','un','para','con','no','una','su','al','lo','como','mas','pero','sus','le','ya','o','este','si','porque','esta','entre','cuando','muy','sin','sobre','tambien','me','hasta','hay','donde','quien','desde','todo','nos','durante','todos','uno','les','ni','contra','otros','ese','eso','ante','ellos','e','esto','mi','antes','algunos','que','unos','yo','otro','otras','otra','el','tanto','esa','estos','mucho','quienes','nada','muchos','cual','poco','ella','estar','estas','algunas','algo','nosotros','mi','mis','tu','te','ti','tus','ellas','nos','vosotros','vosotras','os','mio','mia','mios','mias','tuyo','tuya','tuyos','tuyas','suyo','suya','suyos','suyas','nuestro','nuestra','nuestros','nuestras','vuestro','vuestra','vuestros','vuestras','esos','esas','estoy','esta','estamos','estais','estan','estaba','estabas','estabamos','estabais','estaban','estuve','estuviste','estuvo','estuvimos','estuvisteis','estuvieron','estuviera','estuvieras','estuvieramos','estuvierais','estuvieran','estuviese','estuvieses','estuviesemos','estuvieseis','estuviesen','estando','estado','estada','estados','estadas','estad','he','has','ha','hemos','habeis','han','haya','hayas','hayamos','hayais','hayan','habia','habias','habiamos','habiais','habian','hube','hubiste','hubo','hubimos','hubisteis','hubieron','hubiera','hubieras','hubieramos','hubierais','hubieran','hubiese','hubieses','hubiesemos','hubieseis','hubiesen','habiendo','habido','habida','habidos','habidas','soy','eres','es','somos','sois','son','sea','seas','seamos','seais','sean','era','eras','eramos','erais','eran','fui','fuiste','fue','fuimos','fuisteis','fueron','fuera','fueras','fueramos','fuerais','fueran','fuese','fueses','fuesemos','fueseis','fuesen','siendo','sido','tengo','tienes','tiene','tenemos','teneis','tienen','tenga','tengas','tengamos','tengais','tengan','tenia','tenias','teniamos','teniais','tenian','tuve','tuviste','tuvo','tuvimos','tuvisteis','tuvieron','tuviera','tuvieras','tuvieramos','tuvierais','tuvieran','tuviese','tuvieses','tuviesemos','tuvieseis','tuviesen','teniendo','tenido','tenida','tenidos','tenidas'
    ];
}

function construirNube(array $textos, int $maxPalabras = 40): array
{
    $stopwords = array_fill_keys(obtenerStopwords(), true);
    $conteo = [];

    foreach ($textos as $texto) {
        $texto = normalizarTexto((string)$texto);
        if ($texto === '') {
            continue;
        }
        $palabras = preg_split('/\s+/', $texto) ?: [];
        foreach ($palabras as $palabra) {
            $palabra = trim($palabra);
            if ($palabra === '' || strlen($palabra) < 3) {
                continue;
            }
            if (isset($stopwords[$palabra])) {
                continue;
            }
            $conteo[$palabra] = ($conteo[$palabra] ?? 0) + 1;
        }
    }

    if (!$conteo) {
        return [];
    }

    arsort($conteo);
    $conteo = array_slice($conteo, 0, $maxPalabras, true);

    $valores = array_values($conteo);
    $min = min($valores);
    $max = max($valores);
    $minFont = 12;
    $maxFont = 34;

    $resultado = [];
    foreach ($conteo as $palabra => $total) {
        $size = $min === $max ? ($minFont + $maxFont) / 2 : $minFont + (($total - $min) * ($maxFont - $minFont) / ($max - $min));
        $resultado[] = [
            'word' => $palabra,
            'count' => $total,
            'size' => round($size, 1)
        ];
    }

    return $resultado;
}

function colorParaPalabra(string $palabra): string
{
    $palette = [
        '#2f6f9e', '#f4a340', '#e36c6c', '#3a8f6b', '#b07cc6',
        '#4f81bd', '#c0504d', '#9bbb59', '#8064a2', '#4bacc6'
    ];
    $hash = crc32($palabra);
    $index = (int)($hash % count($palette));
    return $palette[$index];
}

function rotacionParaPalabra(string $palabra): int
{
    $rotaciones = [-10, -5, 0, 5, 10];
    $hash = crc32('r' . $palabra);
    $index = (int)($hash % count($rotaciones));
    return $rotaciones[$index];
}

function obtenerTextosNube(PDO $pdo, string $tipo, string $whereSql, array $params): array
{
    $textos = [];
    $whereTipo = $whereSql ? ($whereSql . " AND d.tipo = ?") : "WHERE d.tipo = ?";
    $paramsTipo = array_merge($params, [$tipo]);

    $sql = "
        SELECT d.fragmento, d.tema
        FROM fb_analisis_ia_detalle d
        JOIN fb_respuestas r ON r.id = d.respuesta_id
        $whereTipo
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($paramsTipo);
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        if (!empty($row['fragmento'])) {
            $textos[] = $row['fragmento'];
        }
        if (!empty($row['tema'])) {
            $textos[] = $row['tema'];
        }
    }

    return $textos;
}

$textosPositivos = obtenerTextosNube($pdo, 'positivo', $whereSql, $params);
$textosNegativos = obtenerTextosNube($pdo, 'negativo', $whereSql, $params);
$nubePositiva = construirNube($textosPositivos);
$nubeNegativa = construirNube($textosNegativos);

$positivas = 0;
$negativas = 0;
$neutras   = 0;
$mixtas    = 0;
$sinAnalisis = 0;

foreach ($sentimientos as $s) {
    if ($s['sentimiento'] === 'positivo')     $positivas = (int)$s['total'];
    if ($s['sentimiento'] === 'negativo')     $negativas = (int)$s['total'];
    if ($s['sentimiento'] === 'neutro')       $neutras   = (int)$s['total'];
    if ($s['sentimiento'] === 'mixto')        $mixtas    = (int)$s['total'];
    if ($s['sentimiento'] === 'sin_analisis') $sinAnalisis = (int)$s['total'];
}

$hallazgosPositivos = 0;
$hallazgosNegativos = 0;

foreach ($fragmentos as $f) {
    if ($f['tipo'] === 'positivo') $hallazgosPositivos = (int)$f['total'];
    if ($f['tipo'] === 'negativo') $hallazgosNegativos = (int)$f['total'];
}

$temaPrincipal = $temas[0]['tema'] ?? 'Sin datos';
function armarTooltipResumen(array $items, string $textoVacio): string
{
    if (empty($items)) {
        return $textoVacio;
    }

    $lineas = [];
    foreach ($items as $item) {
        $tema = trim((string)($item['tema'] ?? 'Sin clasificar'));
        $total = (int)($item['total'] ?? 0);
        $lineas[] = $tema . ' (' . $total . ')';
    }

    return implode(" | ", $lineas);
}

$tooltipPositivos = armarTooltipResumen($topPositivos, 'No hay hallazgos positivos registrados.');
$tooltipNegativos = armarTooltipResumen($topNegativos, 'No hay hallazgos negativos registrados.');
?>

<div class="container mb-5">
  <div class="card rounded-4 mb-4">
    <div class="card-body">
      <form class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Desde</label>
          <input type="date" name="desde" class="form-control" value="<?= htmlspecialchars($desde) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Hasta</label>
          <input type="date" name="hasta" class="form-control" value="<?= htmlspecialchars($hasta) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Campaña</label>
          <select name="campana_id" class="form-select">
            <option value="0">Todas</option>
            <?php foreach ($campanas as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $campanaId === (int)$c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 align-self-end">
          <button class="btn btn-primary w-100">
            <i class="bi bi-search me-2"></i>Filtrar
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-2">
      <div class="card rounded-4 h-100 kpi-card">
        <div class="card-body d-flex justify-content-between align-items-start">
          <div>
            <small class="text-muted">Total respuestas</small>
            <h3 class="text-primary"><?= (int)$kpi['total_respuestas'] ?></h3>
          </div>
          <div class="stat-icon"><i class="bi bi-chat-left-text"></i></div>
        </div>
      </div>
    </div>

    <div class="col-md-6 col-xl-2">
      <div class="card rounded-4 h-100 kpi-card">
        <div class="card-body d-flex justify-content-between align-items-start">
          <div>
            <small class="text-muted">Promedio</small>
            <h3 class="text-primary"><?= htmlspecialchars((string)($kpi['promedio_calificacion'] ?? '0')) ?></h3>
          </div>
          <div class="stat-icon"><i class="bi bi-star"></i></div>
        </div>
      </div>
    </div>

    <div class="col-md-6 col-xl-2">
      <div class="card rounded-4 h-100 kpi-card">
        <div class="card-body d-flex justify-content-between align-items-start">
          <div>
            <small class="text-muted">Positivas</small>
            <h3 class="text-success"><?= $positivas ?></h3>
          </div>
          <div class="stat-icon"><i class="bi bi-emoji-smile"></i></div>
        </div>
      </div>
    </div>

    <div class="col-md-6 col-xl-2">
      <div class="card rounded-4 h-100 kpi-card">
        <div class="card-body d-flex justify-content-between align-items-start">
          <div>
            <small class="text-muted">Negativas</small>
            <h3 class="text-danger"><?= $negativas ?></h3>
          </div>
          <div class="stat-icon"><i class="bi bi-emoji-frown"></i></div>
        </div>
      </div>
    </div>

    <div class="col-md-6 col-xl-2">
      <div class="card rounded-4 h-100 kpi-card">
        <div class="card-body d-flex justify-content-between align-items-start">
          <div>
            <small class="text-muted">Mixtas</small>
            <h3 class="text-warning"><?= $mixtas ?></h3>
          </div>
          <div class="stat-icon"><i class="bi bi-emoji-neutral"></i></div>
        </div>
      </div>
    </div>

    <div class="col-md-6 col-xl-2">
      <div class="card rounded-4 h-100 kpi-card">
        <div class="card-body d-flex justify-content-between align-items-start">
          <div>
            <small class="text-muted">Tema principal</small>
            <h6 class="text-primary mb-0 mt-1"><?= htmlspecialchars($temaPrincipal) ?></h6>
          </div>
          <div class="stat-icon"><i class="bi bi-tags"></i></div>
        </div>
      </div>
    </div>
  </div>

<div class="row g-3 mb-4">
  <div class="col-md-6 col-xl-4">
    <div class="card rounded-4 h-100 kpi-card">
      <div class="card-body d-flex justify-content-between align-items-start">
        <div>
          <small class="text-muted">
            Hallazgos positivos
            <i class="bi bi-info-circle ms-1 text-success"
               data-bs-toggle="tooltip"
               data-bs-placement="top"
               data-bs-title="<?= htmlspecialchars($tooltipPositivos) ?>"></i>
          </small>
          <h3 class="text-success"><?= $hallazgosPositivos ?></h3>
        </div>
        <div class="stat-icon"><i class="bi bi-hand-thumbs-up"></i></div>
      </div>
    </div>
  </div>

  <div class="col-md-6 col-xl-4">
    <div class="card rounded-4 h-100 kpi-card">
      <div class="card-body d-flex justify-content-between align-items-start">
        <div>
          <small class="text-muted">
            Hallazgos negativos
            <i class="bi bi-info-circle ms-1 text-danger"
               data-bs-toggle="tooltip"
               data-bs-placement="top"
               data-bs-title="<?= htmlspecialchars($tooltipNegativos) ?>"></i>
          </small>
          <h3 class="text-danger"><?= $hallazgosNegativos ?></h3>
        </div>
        <div class="stat-icon"><i class="bi bi-hand-thumbs-down"></i></div>
      </div>
    </div>
  </div>

  <div class="col-md-12 col-xl-4">
    <div class="card rounded-4 h-100 kpi-card">
      <div class="card-body d-flex justify-content-between align-items-start">
        <div>
          <small class="text-muted">Sin análisis IA</small>
          <h3 class="text-secondary"><?= $sinAnalisis ?></h3>
        </div>
        <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
      </div>
    </div>
  </div>
</div>

  <div class="row g-4 mb-4">
    <div class="col-lg-4">
      <div class="card rounded-4">
        <div class="card-body">
          <h6 class="section-title mb-3">Distribución de sentimiento</h6>
          <div style="position:relative; height:280px;">
            <canvas id="chartSentimientos"></canvas>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card rounded-4">
        <div class="card-body">
          <h6 class="section-title mb-3">Temas frecuentes</h6>
          <div style="position:relative; height:320px;">
            <canvas id="chartTemas"></canvas>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card rounded-4">
        <div class="card-body">
          <h6 class="section-title mb-3">Hallazgos por fragmento</h6>
          <div style="position:relative; height:320px;">
            <canvas id="chartFragmentos"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-6">
      <div class="card rounded-4 h-100">
        <div class="card-body">
          <h6 class="section-title mb-3">Nube de palabras positivas</h6>
          <?php if (!$nubePositiva): ?>
            <div class="text-muted">No hay suficientes datos.</div>
          <?php else: ?>
            <div class="d-flex flex-wrap gap-2 word-cloud">
              <?php foreach ($nubePositiva as $item): ?>
                <?php
                  $color = colorParaPalabra((string)$item['word']);
                  $rot = rotacionParaPalabra((string)$item['word']);
                ?>
                <span
                  class="word-cloud-item"
                  style="font-size:<?= htmlspecialchars((string)$item['size']) ?>px; color:<?= htmlspecialchars($color) ?>; transform: rotate(<?= (int)$rot ?>deg);"
                  title="<?= htmlspecialchars($item['word']) ?> (<?= (int)$item['count'] ?>)"
                >
                  <?= htmlspecialchars($item['word']) ?>
                </span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card rounded-4 h-100">
        <div class="card-body">
          <h6 class="section-title mb-3">Nube de palabras negativas</h6>
          <?php if (!$nubeNegativa): ?>
            <div class="text-muted">No hay suficientes datos.</div>
          <?php else: ?>
            <div class="d-flex flex-wrap gap-2 word-cloud">
              <?php foreach ($nubeNegativa as $item): ?>
                <?php
                  $color = colorParaPalabra((string)$item['word']);
                  $rot = rotacionParaPalabra((string)$item['word']);
                ?>
                <span
                  class="word-cloud-item"
                  style="font-size:<?= htmlspecialchars((string)$item['size']) ?>px; color:<?= htmlspecialchars($color) ?>; transform: rotate(<?= (int)$rot ?>deg);"
                  title="<?= htmlspecialchars($item['word']) ?> (<?= (int)$item['count'] ?>)"
                >
                  <?= htmlspecialchars($item['word']) ?>
                </span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="card rounded-4">
    <div class="card-body">
      <h6 class="section-title mb-3">Últimos feedback</h6>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Campaña</th>
              <th>Calificación</th>
              <th>Comentario</th>
              <th>Sentimiento</th>
              <th>Tema</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$ultimas): ?>
              <tr><td colspan="7" class="text-center text-muted">No hay registros.</td></tr>
            <?php else: ?>
              <?php foreach ($ultimas as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['fecha_respuesta']) ?></td>
                  <td><?= htmlspecialchars($r['campana']) ?></td>
                  <td><span class="badge text-bg-primary"><?= (int)$r['calificacion'] ?></span></td>
                  <td class="comment-preview"><?= htmlspecialchars($r['comentario']) ?></td>
                  <td>
                    <?php
                      $sent = $r['sentimiento'] ?? '';
                      $cls = 'badge-soft-secondary';
                      if ($sent === 'positivo') $cls = 'badge-soft-success';
                      elseif ($sent === 'negativo') $cls = 'badge-soft-danger';
                      elseif ($sent === 'mixto') $cls = 'badge-soft-warning';
                      elseif ($sent === 'neutro') $cls = 'badge-soft-secondary';
                    ?>
                    <span class="badge <?= $cls ?>"><?= htmlspecialchars($sent ?: 'Sin análisis') ?></span>
                  </td>
                  <td><?= htmlspecialchars($r['tema_principal'] ?? '—') ?></td>
                  <td>
                    <a href="detalle_feedback.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary">
                      Ver
                    </a>
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

<script>
const dataSent = <?= json_encode($sentimientos, JSON_UNESCAPED_UNICODE) ?>;
const dataTemas = <?= json_encode($temas, JSON_UNESCAPED_UNICODE) ?>;
const dataFragmentos = <?= json_encode($fragmentos, JSON_UNESCAPED_UNICODE) ?>;

new Chart(document.getElementById('chartSentimientos'), {
  type: 'doughnut',
  data: {
    labels: dataSent.map(x => x.sentimiento),
    datasets: [{
      data: dataSent.map(x => parseInt(x.total, 10))
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false
  }
});

new Chart(document.getElementById('chartTemas'), {
  type: 'bar',
  data: {
    labels: dataTemas.map(x => x.tema),
    datasets: [{
      label: 'Total',
      data: dataTemas.map(x => parseInt(x.total, 10))
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false
  }
});

new Chart(document.getElementById('chartFragmentos'), {
  type: 'bar',
  data: {
    labels: dataFragmentos.map(x => x.tipo),
    datasets: [{
      label: 'Hallazgos',
      data: dataFragmentos.map(x => parseInt(x.total, 10))
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false
  }
});

</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  [...tooltipTriggerList].forEach(function (el) {
    new bootstrap.Tooltip(el);
  });
});
</script>
