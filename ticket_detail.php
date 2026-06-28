<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /tickets.php'); exit; }

$stmt = db()->prepare('
    SELECT t.*, c.name AS company_name, c.rfc AS company_rfc,
           c.email AS company_email, c.zip_code, c.tax_regime
    FROM tickets t
    LEFT JOIN companies c ON c.id = t.company_id
    WHERE t.id = ?
');
$stmt->execute([$id]);
$t = $stmt->fetch();
if (!$t) { header('Location: /tickets.php'); exit; }

$logs = db()->prepare('SELECT * FROM stamp_logs WHERE ticket_id=? ORDER BY attempted_at DESC');
$logs->execute([$id]);
$logs = $logs->fetchAll();

layout_head('Ticket #' . $id);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="/tickets.php" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i></a>
    <h2 class="d-inline mb-0">Ticket #<?= $id ?></h2>
    <span class="ms-2"><?= status_badge($t['status']) ?></span>
  </div>
  <?php if ($t['status'] !== 'stamped'): ?>
  <button id="btnStamp" class="btn btn-success btn-lg">
    <i class="bi bi-stamp me-1"></i>Timbrar ahora
  </button>
  <?php endif; ?>
</div>

<div class="row g-4">

  <!-- Left: image -->
  <div class="col-md-5">
    <?php if ($t['image_path']): ?>
    <div class="card shadow-sm">
      <div class="card-body text-center p-2">
        <img src="<?= htmlspecialchars($t['image_path']) ?>" class="ticket-img w-100" alt="Ticket">
      </div>
    </div>
    <?php else: ?>
    <div class="card shadow-sm">
      <div class="card-body text-center text-muted py-5">
        <i class="bi bi-image fs-1"></i><br>Sin imagen
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right: details -->
  <div class="col-md-7">

    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-receipt me-1"></i>Datos del ticket</span>
        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEdit">
          <i class="bi bi-pencil me-1"></i>Editar
        </button>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-5">Establecimiento</dt>
          <dd class="col-sm-7"><?= htmlspecialchars($t['store_name'] ?? '—') ?></dd>

          <dt class="col-sm-5">RFC establecimiento</dt>
          <dd class="col-sm-7"><code><?= htmlspecialchars($t['store_rfc'] ?? '—') ?></code></dd>

          <dt class="col-sm-5">No. ticket / folio</dt>
          <dd class="col-sm-7"><?= htmlspecialchars($t['ticket_number'] ?? '—') ?></dd>

          <dt class="col-sm-5">Serie</dt>
          <dd class="col-sm-7"><?= htmlspecialchars($t['serie'] ?? '—') ?></dd>

          <dt class="col-sm-5">Fecha de compra</dt>
          <dd class="col-sm-7"><?= $t['purchase_date'] ? date('d/m/Y', strtotime($t['purchase_date'])) : '—' ?></dd>

          <dt class="col-sm-5">Subtotal</dt>
          <dd class="col-sm-7">$<?= number_format($t['subtotal'] ?? 0, 2) ?></dd>

          <dt class="col-sm-5">IVA</dt>
          <dd class="col-sm-7">$<?= number_format($t['tax'] ?? 0, 2) ?></dd>

          <dt class="col-sm-5 fw-bold">Total</dt>
          <dd class="col-sm-7 fw-bold fs-5">$<?= number_format($t['total'] ?? 0, 2) ?></dd>
        </dl>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold"><i class="bi bi-building me-1"></i>Empresa que timbra</div>
      <div class="card-body">
        <?php if ($t['company_name']): ?>
        <dl class="row mb-0">
          <dt class="col-sm-5">Razón social</dt>
          <dd class="col-sm-7"><?= htmlspecialchars($t['company_name']) ?></dd>
          <dt class="col-sm-5">RFC</dt>
          <dd class="col-sm-7"><code><?= htmlspecialchars($t['company_rfc']) ?></code></dd>
          <dt class="col-sm-5">Email CFDI</dt>
          <dd class="col-sm-7"><?= htmlspecialchars($t['company_email'] ?? '—') ?></dd>
          <dt class="col-sm-5">C.P.</dt>
          <dd class="col-sm-7"><?= htmlspecialchars($t['zip_code'] ?? '—') ?></dd>
          <dt class="col-sm-5">Régimen fiscal</dt>
          <dd class="col-sm-7"><?= htmlspecialchars($t['tax_regime'] ?? '—') ?></dd>
        </dl>
        <?php else: ?>
        <p class="text-muted mb-0">Sin empresa asignada</p>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($t['notes']): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">Notas</div>
      <div class="card-body"><?= nl2br(htmlspecialchars($t['notes'])) ?></div>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- CFDI files -->
<h4 class="mt-5 mb-3"><i class="bi bi-file-earmark-zip me-2"></i>Archivos CFDI</h4>
<div class="card shadow-sm mb-4">
  <div class="card-body">
    <div class="row g-3 align-items-center">

      <!-- XML -->
      <div class="col-md-4">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-file-earmark-code fs-3 text-primary"></i>
          <div>
            <div class="fw-semibold">XML (CFDI)</div>
            <?php if ($t['xml_path']): ?>
              <a href="<?= htmlspecialchars($t['xml_path']) ?>" download class="btn btn-sm btn-outline-primary mt-1">
                <i class="bi bi-download me-1"></i>Descargar XML
              </a>
            <?php else: ?>
              <span class="text-muted small">No adjunto</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- PDF -->
      <div class="col-md-4">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-file-earmark-pdf fs-3 text-danger"></i>
          <div>
            <div class="fw-semibold">PDF (Factura)</div>
            <?php if ($t['pdf_path']): ?>
              <a href="<?= htmlspecialchars($t['pdf_path']) ?>" target="_blank" class="btn btn-sm btn-outline-danger mt-1">
                <i class="bi bi-eye me-1"></i>Ver PDF
              </a>
            <?php else: ?>
              <span class="text-muted small">No adjunto</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Manual upload -->
      <div class="col-md-4">
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#uploadCfdi">
          <i class="bi bi-paperclip me-1"></i>Adjuntar archivos
        </button>
        <div class="collapse mt-2" id="uploadCfdi">
          <form id="formCfdi" enctype="multipart/form-data">
            <input type="hidden" name="ticket_id" value="<?= $id ?>">
            <div class="mb-2">
              <label class="form-label small">XML</label>
              <input type="file" name="xml" accept=".xml" class="form-control form-control-sm">
            </div>
            <div class="mb-2">
              <label class="form-label small">PDF</label>
              <input type="file" name="pdf" accept=".pdf" class="form-control form-control-sm">
            </div>
            <button type="submit" class="btn btn-sm btn-primary">Subir</button>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Stamp log -->
<?php if ($logs): ?>
<h4 class="mt-4 mb-3"><i class="bi bi-journal-text me-2"></i>Historial de intentos de timbrado</h4>
<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table mb-0">
      <thead class="table-dark">
        <tr><th>Fecha</th><th>Resultado</th><th>Mensaje</th><th>Screenshot</th></tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $log): ?>
        <tr>
          <td class="text-muted small"><?= date('d/m/Y H:i:s', strtotime($log['attempted_at'])) ?></td>
          <td>
            <?php if ($log['result'] === 'success'): ?>
              <span class="badge bg-success">Éxito</span>
            <?php else: ?>
              <span class="badge bg-danger">Error</span>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($log['message'] ?? '') ?></td>
          <td>
            <?php if ($log['screenshot']): ?>
            <a href="<?= htmlspecialchars($log['screenshot']) ?>" target="_blank">
              <img src="<?= htmlspecialchars($log['screenshot']) ?>" style="height:48px;border-radius:4px">
            </a>
            <?php else: ?>—<?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Stamp result alert -->
<div id="stampResult" class="mt-4 d-none"></div>

<!-- Edit modal -->
<?php
$companies = db()->query('SELECT id, name, rfc FROM companies ORDER BY name')->fetchAll();
?>
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil me-1"></i>Editar ticket #<?= $id ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body row g-3">

        <div class="col-md-8">
          <label class="form-label fw-semibold">Establecimiento</label>
          <input type="text" id="e_store_name" class="form-control" value="<?= htmlspecialchars($t['store_name'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">RFC establecimiento</label>
          <input type="text" id="e_store_rfc" class="form-control" style="text-transform:uppercase"
                 value="<?= htmlspecialchars($t['store_rfc'] ?? '') ?>">
        </div>

        <div class="col-md-5">
          <label class="form-label fw-semibold">No. ticket / folio</label>
          <input type="text" id="e_ticket_number" class="form-control" value="<?= htmlspecialchars($t['ticket_number'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Serie</label>
          <input type="text" id="e_serie" class="form-control" value="<?= htmlspecialchars($t['serie'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Fecha de compra</label>
          <input type="date" id="e_purchase_date" class="form-control" value="<?= htmlspecialchars($t['purchase_date'] ?? '') ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label fw-semibold">Subtotal</label>
          <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" id="e_subtotal" class="form-control" step="0.01"
                   value="<?= $t['subtotal'] ?? '' ?>">
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">IVA</label>
          <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" id="e_tax" class="form-control" step="0.01"
                   value="<?= $t['tax'] ?? '' ?>">
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Total</label>
          <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" id="e_total" class="form-control" step="0.01"
                   value="<?= $t['total'] ?? '' ?>">
          </div>
        </div>

        <div class="col-md-8">
          <label class="form-label fw-semibold">Empresa que timbra</label>
          <select id="e_company_id" class="form-select">
            <option value="">— Sin asignar —</option>
            <?php foreach ($companies as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $c['id'] == $t['company_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['rfc']) ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Estado</label>
          <select id="e_status" class="form-select">
            <option value="pending" <?= $t['status']==='pending' ? 'selected':'' ?>>Pendiente</option>
            <option value="stamped" <?= $t['status']==='stamped' ? 'selected':'' ?>>Timbrado</option>
            <option value="failed"  <?= $t['status']==='failed'  ? 'selected':'' ?>>Fallido</option>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label">Notas</label>
          <textarea id="e_notes" class="form-control" rows="2"><?= htmlspecialchars($t['notes'] ?? '') ?></textarea>
        </div>

        <div id="editAlert" class="col-12 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" id="btnSaveEdit" class="btn btn-primary">
          <i class="bi bi-floppy me-1"></i>Guardar cambios
        </button>
      </div>
    </div>
  </div>
</div>

<script>
const btnStamp   = document.getElementById('btnStamp');
const stampResult = document.getElementById('stampResult');
const TICKET_ID  = <?= $id ?>;

if (btnStamp) {
  btnStamp.addEventListener('click', async () => {
    btnStamp.disabled = true;
    btnStamp.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Iniciando...';

    // Launch the background script
    let res, data;
    try {
      res  = await fetch('/stamp.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ ticket_id: TICKET_ID }),
      });
      data = await res.json();
    } catch (e) {
      showResult(false, 'Error de red: ' + e.message); return;
    }

    if (!data.success && !data.polling) {
      showResult(false, data.message); return;
    }

    // Start polling for result
    btnStamp.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Navegador procesando…';
    setStatus('El navegador está llenando el formulario. No cierres esa ventana.');
    poll();
  });
}

async function poll() {
  await new Promise(r => setTimeout(r, 4000));
  try {
    const res  = await fetch(`/api/stamp_status.php?ticket_id=${TICKET_ID}`);
    const data = await res.json();

    if (!data.done) {
      setStatus(data.message || 'El navegador sigue procesando…');
      poll(); // keep polling
      return;
    }

    showResult(data.success, data.message);
  } catch (e) {
    setStatus('Error al consultar estado: ' + e.message);
    poll();
  }
}

function setStatus(msg) {
  stampResult.classList.remove('d-none');
  stampResult.innerHTML = `<div class="alert alert-info"><span class="spinner-border spinner-border-sm me-2"></span>${msg}</div>`;
}

function showResult(success, message) {
  stampResult.classList.remove('d-none');
  if (success) {
    stampResult.innerHTML = `<div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>${message}</div>`;
    setTimeout(() => location.reload(), 2000);
  } else {
    stampResult.innerHTML = `<div class="alert alert-danger"><i class="bi bi-x-circle me-1"></i><strong>Error:</strong> ${message}</div>`;
    if (btnStamp) {
      btnStamp.disabled = false;
      btnStamp.innerHTML = '<i class="bi bi-stamp me-1"></i>Reintentar';
    }
  }
}
</script>

<script>
document.getElementById('btnSaveEdit')?.addEventListener('click', async () => {
  const btn   = document.getElementById('btnSaveEdit');
  const alert = document.getElementById('editAlert');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

  const body = {
    id:            <?= $id ?>,
    store_name:    document.getElementById('e_store_name').value,
    store_rfc:     document.getElementById('e_store_rfc').value.toUpperCase(),
    ticket_number: document.getElementById('e_ticket_number').value,
    serie:         document.getElementById('e_serie').value,
    purchase_date: document.getElementById('e_purchase_date').value,
    subtotal:      document.getElementById('e_subtotal').value,
    tax:           document.getElementById('e_tax').value,
    total:         document.getElementById('e_total').value,
    company_id:    document.getElementById('e_company_id').value,
    status:        document.getElementById('e_status').value,
    notes:         document.getElementById('e_notes').value,
  };

  const res  = await fetch('/api/update_ticket.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(body),
  });
  const data = await res.json();

  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar cambios';

  if (data.error) {
    alert.className = 'col-12 alert alert-danger';
    alert.textContent = data.error;
    alert.classList.remove('d-none');
  } else {
    location.reload(); // reload to show updated values
  }
});
</script>

<script>
document.getElementById('formCfdi')?.addEventListener('submit', async e => {
  e.preventDefault();
  const fd  = new FormData(e.target);
  const res = await fetch('/api/attach_cfdi.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.error) { alert('Error: ' + data.error); return; }
  location.reload();
});
</script>

<?php layout_foot(); ?>
