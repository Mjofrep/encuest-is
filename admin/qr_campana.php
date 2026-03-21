<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    $pageTitle = 'QR Campaña';
    require __DIR__ . '/../includes/header_admin.php';
    ?>
    <div class="container mb-5">
      <div class="card rounded-4">
        <div class="card-body text-center p-5">
          <i class="bi bi-exclamation-circle text-warning" style="font-size:3rem;"></i>
          <h4 class="mt-3 text-primary">Token no informado</h4>
          <p class="text-muted mb-4">Debes ingresar desde la página de campañas para generar el QR correcto.</p>
          <a href="campanas.php" class="btn btn-primary">
            <i class="bi bi-arrow-left me-2"></i>Volver a campañas
          </a>
        </div>
      </div>
    </div>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$urlDestino = 'https://www.noetica.cl/feedback/index.php?token=' . $token;
$tamano = '320x320';

$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size='
       . $tamano
       . '&data=' . urlencode($urlDestino);

$pageTitle = 'QR Campaña';
require __DIR__ . '/../includes/header_admin.php';
?>

<div class="container mb-5">
  <div class="card rounded-4">
    <div class="card-body text-center p-4 p-md-5">
      <h4 class="section-title mb-2">QR de campaña</h4>
      <p class="text-muted mb-4">Escanea este código para abrir el formulario de feedback.</p>

      <div class="mb-4">
        <img
          src="<?= htmlspecialchars($qrUrl) ?>"
          alt="QR Campaña"
          class="img-fluid"
          style="max-width:320px; background:#fff; padding:12px; border-radius:16px; box-shadow:0 2px 8px rgba(0,0,0,.08);"
        >
      </div>





        <a href="campanas.php" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-left me-2"></i>Volver a campañas
        </a>
      </div>

      <div id="msgCopia" class="mt-3 text-success fw-semibold" style="display:none;"></div>
    </div>
  </div>
</div>

<script>
function copiarURL() {
  const input = document.getElementById('urlCampana');
  navigator.clipboard.writeText(input.value).then(() => {
    mostrarMensaje('URL copiada al portapapeles.');
  }).catch(() => {
    input.select();
    input.setSelectionRange(0, 99999);
    document.execCommand('copy');
    mostrarMensaje('URL copiada al portapapeles.');
  });
}

function mostrarMensaje(texto) {
  const msg = document.getElementById('msgCopia');
  msg.textContent = texto;
  msg.style.display = 'block';
  setTimeout(() => {
    msg.style.display = 'none';
  }, 2500);
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>