<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';

$companies = db()->query('SELECT id, name, rfc FROM companies ORDER BY name')->fetchAll();
layout_head('Subir ticket');
?>

<div class="row justify-content-center">
<div class="col-lg-9">

<h2 class="mb-4"><i class="bi bi-upload me-2"></i>Subir ticket</h2>

<?php if (empty($companies)): ?>
<div class="alert alert-warning">
  <i class="bi bi-exclamation-triangle me-1"></i>
  No hay empresas registradas. <a href="/companies.php">Agrega una empresa</a> antes de subir tickets.
</div>
<?php endif; ?>

<!-- Step 1: Drop zone -->
<div class="card shadow-sm mb-4" id="stepUpload">
  <div class="card-body">
    <h5 class="card-title mb-3"><span class="badge bg-primary me-2">1</span>Selecciona la imagen del ticket</h5>
    <div id="dropZone" class="border border-2 border-dashed rounded p-5 text-center text-muted" style="cursor:pointer;transition:background .2s">
      <i class="bi bi-image fs-1 d-block mb-2"></i>
      <p class="mb-1">Arrastra la foto aquí o haz clic para seleccionar</p>
      <small>JPG, PNG, WEBP — máx. 10 MB</small>
      <input type="file" id="fileInput" accept="image/*" class="d-none">
    </div>
    <div id="previewWrap" class="mt-3 text-center d-none">
      <img id="previewImg" src="" class="ticket-img shadow" style="max-height:300px">
    </div>
    <div class="mt-3">
      <button id="btnExtract" class="btn btn-success" disabled>
        <i class="bi bi-cpu me-1"></i>Extraer datos con IA
      </button>
      <span id="extractSpinner" class="ms-2 d-none">
        <span class="spinner-border spinner-border-sm"></span> Procesando...
      </span>
    </div>
  </div>
</div>

<!-- Step 2: Review form -->
<div class="card shadow-sm d-none" id="stepForm">
  <div class="card-body">
    <h5 class="card-title mb-3"><span class="badge bg-primary me-2">2</span>Revisa y corrige los datos</h5>

    <div id="aiAlert" class="alert alert-success d-none">
      <i class="bi bi-check-circle me-1"></i>Datos extraídos automáticamente. Verifica antes de guardar.
    </div>

    <form id="formTicket" class="row g-3">
      <input type="hidden" id="imagePath" name="image_path">

      <div class="col-md-8">
        <label class="form-label fw-semibold">Nombre del establecimiento *</label>
        <input type="text" id="storeName" name="store_name" class="form-control" required>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">RFC del establecimiento</label>
        <input type="text" id="storeRfc" name="store_rfc" class="form-control" style="text-transform:uppercase">
      </div>

      <div class="col-md-4">
        <label class="form-label fw-semibold">No. de ticket / folio</label>
        <input type="text" id="ticketNumber" name="ticket_number" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold">Serie</label>
        <input type="text" id="serie" name="serie" class="form-control">
      </div>
      <div class="col-md-5">
        <label class="form-label fw-semibold">Fecha de compra *</label>
        <input type="date" id="purchaseDate" name="purchase_date" class="form-control" required>
      </div>

      <div class="col-md-4">
        <label class="form-label fw-semibold">Subtotal</label>
        <div class="input-group">
          <span class="input-group-text">$</span>
          <input type="number" id="subtotal" name="subtotal" class="form-control" step="0.01" min="0">
        </div>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">IVA</label>
        <div class="input-group">
          <span class="input-group-text">$</span>
          <input type="number" id="tax" name="tax" class="form-control" step="0.01" min="0">
        </div>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Total *</label>
        <div class="input-group">
          <span class="input-group-text">$</span>
          <input type="number" id="total" name="total" class="form-control" step="0.01" min="0" required>
        </div>
      </div>

      <div class="col-12">
        <label class="form-label fw-semibold">Empresa que timbra *</label>
        <select id="companyId" name="company_id" class="form-select" required>
          <option value="">— Selecciona una empresa —</option>
          <?php foreach ($companies as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['rfc']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12">
        <label class="form-label">Notas</label>
        <textarea id="notes" name="notes" class="form-control" rows="2"></textarea>
      </div>

      <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-floppy me-1"></i>Guardar ticket
        </button>
        <span id="saveSpinner" class="d-none align-self-center">
          <span class="spinner-border spinner-border-sm"></span> Guardando...
        </span>
      </div>
    </form>
  </div>
</div>

</div>
</div>

<script>
const dropZone   = document.getElementById('dropZone');
const fileInput  = document.getElementById('fileInput');
const previewImg = document.getElementById('previewImg');
const previewWrap = document.getElementById('previewWrap');
const btnExtract = document.getElementById('btnExtract');
const stepForm   = document.getElementById('stepForm');
const aiAlert    = document.getElementById('aiAlert');
let uploadedPath = null;
let uploadedFile = null;

dropZone.addEventListener('click', () => fileInput.click());
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('bg-light'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('bg-light'));
dropZone.addEventListener('drop', e => {
  e.preventDefault();
  dropZone.classList.remove('bg-light');
  handleFile(e.dataTransfer.files[0]);
});
fileInput.addEventListener('change', () => handleFile(fileInput.files[0]));

function handleFile(file) {
  if (!file) return;
  uploadedFile = file;
  const reader = new FileReader();
  reader.onload = e => {
    previewImg.src = e.target.result;
    previewWrap.classList.remove('d-none');
  };
  reader.readAsDataURL(file);
  btnExtract.disabled = false;
}

document.getElementById('btnExtract').addEventListener('click', async () => {
  if (!uploadedFile) return;

  // First upload the image
  document.getElementById('extractSpinner').classList.remove('d-none');
  btnExtract.disabled = true;

  const fd = new FormData();
  fd.append('image', uploadedFile);
  const upRes = await fetch('/api/upload_image.php', { method:'POST', body:fd });
  const upData = await upRes.json();
  if (upData.error) { alert('Error al subir imagen: ' + upData.error); btnExtract.disabled=false; document.getElementById('extractSpinner').classList.add('d-none'); return; }
  uploadedPath = upData.path;
  document.getElementById('imagePath').value = uploadedPath;

  // Then extract with AI
  let exData = {};
  try {
    const exRes = await fetch('/api/extract.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ image_path: uploadedPath })
    });
    exData = await exRes.json();
  } catch (err) {
    exData = { error: 'Respuesta inválida del servidor: ' + err.message };
  }
  document.getElementById('extractSpinner').classList.add('d-none');
  btnExtract.disabled = false;

  if (exData.error) {
    alert('Error de extracción: ' + exData.error);
    stepForm.classList.remove('d-none');
    return;
  }

  const d = exData.data;
  document.getElementById('storeName').value    = d.store_name    || '';
  document.getElementById('storeRfc').value     = (d.store_rfc    || '').toUpperCase();
  document.getElementById('ticketNumber').value = d.ticket_number || '';
  document.getElementById('serie').value        = d.serie         || '';
  document.getElementById('purchaseDate').value = d.purchase_date || '';
  document.getElementById('subtotal').value     = d.subtotal      || '';
  document.getElementById('tax').value          = d.tax           || '';
  document.getElementById('total').value        = d.total         || '';

  aiAlert.classList.remove('d-none');
  stepForm.classList.remove('d-none');
  stepForm.scrollIntoView({ behavior:'smooth' });
});

document.getElementById('formTicket').addEventListener('submit', async e => {
  e.preventDefault();
  document.getElementById('saveSpinner').classList.remove('d-none');

  const fd = new FormData(e.target);
  const body = Object.fromEntries(fd.entries());

  const res = await fetch('/api/save_ticket.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(body)
  });
  const data = await res.json();
  document.getElementById('saveSpinner').classList.add('d-none');

  if (data.error) { alert('Error: ' + data.error); return; }
  window.location.href = '/ticket_detail.php?id=' + data.id;
});
</script>

<?php layout_foot(); ?>
