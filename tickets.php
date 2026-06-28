<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';

$companies = db()->query('SELECT id, name FROM companies ORDER BY name')->fetchAll();

// Filters
$filterStatus  = $_GET['status']  ?? '';
$filterCompany = $_GET['company'] ?? '';
$filterMonth   = $_GET['month']   ?? '';

$where  = ['1=1'];
$params = [];

if ($filterStatus) {
    $where[] = 't.status = :status';
    $params[':status'] = $filterStatus;
}
if ($filterCompany) {
    $where[] = 't.company_id = :company_id';
    $params[':company_id'] = (int)$filterCompany;
}
if ($filterMonth) {
    $where[] = "TO_CHAR(t.purchase_date,'YYYY-MM') = :month";
    $params[':month'] = $filterMonth;
}

$sql = 'SELECT t.*, c.name AS company_name
        FROM tickets t
        LEFT JOIN companies c ON c.id = t.company_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY t.purchase_date DESC, t.created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// Summary counts
$counts = db()->query("SELECT status, COUNT(*) AS n FROM tickets GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

layout_head('Tickets');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="mb-0"><i class="bi bi-list-ul me-2"></i>Tickets</h2>
  <a href="/upload.php" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Subir ticket</a>
</div>

<!-- Summary cards -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card border-warning h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <i class="bi bi-clock fs-2 text-warning"></i>
        <div>
          <div class="fs-3 fw-bold"><?= $counts['pending'] ?? 0 ?></div>
          <div class="text-muted small">Pendientes</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-success h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <i class="bi bi-check-circle fs-2 text-success"></i>
        <div>
          <div class="fs-3 fw-bold"><?= $counts['stamped'] ?? 0 ?></div>
          <div class="text-muted small">Timbrados</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-danger h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <i class="bi bi-x-circle fs-2 text-danger"></i>
        <div>
          <div class="fs-3 fw-bold"><?= $counts['failed'] ?? 0 ?></div>
          <div class="text-muted small">Fallidos</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Filters -->
<form method="get" class="card shadow-sm mb-4">
  <div class="card-body row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small fw-semibold">Estado</label>
      <select name="status" class="form-select form-select-sm">
        <option value="">Todos</option>
        <option value="pending"  <?= $filterStatus==='pending'  ? 'selected':'' ?>>Pendiente</option>
        <option value="stamped"  <?= $filterStatus==='stamped'  ? 'selected':'' ?>>Timbrado</option>
        <option value="failed"   <?= $filterStatus==='failed'   ? 'selected':'' ?>>Fallido</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-semibold">Empresa</label>
      <select name="company" class="form-select form-select-sm">
        <option value="">Todas</option>
        <?php foreach ($companies as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $filterCompany==$c['id'] ? 'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-semibold">Mes (YYYY-MM)</label>
      <input type="month" name="month" class="form-control form-control-sm" value="<?= htmlspecialchars($filterMonth) ?>">
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button class="btn btn-sm btn-primary">Filtrar</button>
      <a href="/tickets.php" class="btn btn-sm btn-outline-secondary">Limpiar</a>
    </div>
  </div>
</form>

<!-- Tickets table -->
<?php if (empty($tickets)): ?>
<div class="alert alert-info">No hay tickets con los filtros seleccionados.</div>
<?php else: ?>
<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-dark">
        <tr>
          <th>#</th>
          <th>Establecimiento</th>
          <th>Fecha compra</th>
          <th>Total</th>
          <th>Empresa</th>
          <th>Estado</th>
          <th>Timbrado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tickets as $t): ?>
        <tr>
          <td class="text-muted small"><?= $t['id'] ?></td>
          <td>
            <strong><?= htmlspecialchars($t['store_name'] ?? '—') ?></strong>
            <?php if ($t['ticket_number']): ?>
            <br><small class="text-muted"><?= htmlspecialchars($t['ticket_number']) ?></small>
            <?php endif; ?>
          </td>
          <td><?= $t['purchase_date'] ? date('d/m/Y', strtotime($t['purchase_date'])) : '—' ?></td>
          <td class="fw-semibold">$<?= number_format($t['total'], 2) ?></td>
          <td><?= htmlspecialchars($t['company_name'] ?? '—') ?></td>
          <td><?= status_badge($t['status']) ?></td>
          <td class="text-muted small">
            <?= $t['stamped_at'] ? date('d/m/Y H:i', strtotime($t['stamped_at'])) : '—' ?>
          </td>
          <td>
            <a href="/ticket_detail.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-eye"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php layout_foot(); ?>
