<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';

$error = '';
$success = '';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    db()->prepare('DELETE FROM companies WHERE id=?')->execute([$id]);
    $success = 'Empresa eliminada.';
}

// Handle save (create or update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['_action'] ?? '', ['create','update'])) {
    $fields = ['name','rfc','email','phone','zip_code','tax_regime','notes'];
    $data   = array_map(fn($f) => trim($_POST[$f] ?? ''), array_combine($fields, $fields));

    if (!$data['name'] || !$data['rfc']) {
        $error = 'Razón social y RFC son obligatorios.';
    } else {
        if (($_POST['_action']) === 'create') {
            db()->prepare('INSERT INTO companies (name,rfc,email,phone,zip_code,tax_regime,notes)
                           VALUES (:name,:rfc,:email,:phone,:zip_code,:tax_regime,:notes)')
                ->execute($data);
            $success = 'Empresa creada.';
        } else {
            $id = (int)($_POST['id'] ?? 0);
            db()->prepare('UPDATE companies SET name=:name,rfc=:rfc,email=:email,phone=:phone,
                           zip_code=:zip_code,tax_regime=:tax_regime,notes=:notes WHERE id=:id')
                ->execute(array_merge($data, ['id' => $id]));
            $success = 'Empresa actualizada.';
        }
    }
}

$companies = db()->query('SELECT * FROM companies ORDER BY name')->fetchAll();

// Load one for editing
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM companies WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}

layout_head('Empresas');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="mb-0"><i class="bi bi-building me-2"></i>Empresas</h2>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalEmpresa">
    <i class="bi bi-plus-lg me-1"></i>Nueva empresa
  </button>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<?php if (empty($companies)): ?>
  <div class="alert alert-info">No hay empresas registradas. Agrega la primera para empezar.</div>
<?php else: ?>
<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead class="table-dark">
        <tr>
          <th>Razón social</th><th>RFC</th><th>Email</th><th>C.P.</th><th>Régimen fiscal</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($companies as $c): ?>
        <tr>
          <td><?= htmlspecialchars($c['name']) ?></td>
          <td><code><?= htmlspecialchars($c['rfc']) ?></code></td>
          <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
          <td><?= htmlspecialchars($c['zip_code'] ?? '') ?></td>
          <td><?= htmlspecialchars($c['tax_regime'] ?? '') ?></td>
          <td class="text-end">
            <a href="?edit=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
            <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar esta empresa?')">
              <input type="hidden" name="_action" value="delete">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Modal: Create / Edit -->
<div class="modal fade" id="modalEmpresa" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= $editing ? 'Editar empresa' : 'Nueva empresa' ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body row g-3">
        <input type="hidden" name="_action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= $editing['id'] ?>"><?php endif; ?>

        <div class="col-md-8">
          <label class="form-label fw-semibold">Razón social *</label>
          <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editing['name'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">RFC *</label>
          <input type="text" name="rfc" class="form-control" required style="text-transform:uppercase"
                 value="<?= htmlspecialchars($editing['rfc'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Email CFDI</label>
          <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($editing['email'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Teléfono</label>
          <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($editing['phone'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Código postal</label>
          <input type="text" name="zip_code" class="form-control" value="<?= htmlspecialchars($editing['zip_code'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Régimen fiscal</label>
          <input type="text" name="tax_regime" class="form-control"
                 placeholder="Ej: 601 - General de Ley Personas Morales"
                 value="<?= htmlspecialchars($editing['tax_regime'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Notas internas</label>
          <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($editing['notes'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<?php if ($editing): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  new bootstrap.Modal(document.getElementById('modalEmpresa')).show();
});
</script>
<?php endif; ?>

<?php layout_foot(); ?>
