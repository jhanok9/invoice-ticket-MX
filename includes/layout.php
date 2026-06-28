<?php
function layout_head(string $title): void { ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($title) ?> — Invoice Tracker</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body { background:#f8f9fa; }
.navbar-brand { font-weight:700; letter-spacing:-.5px; }
.status-pending  { color:#f59e0b; }
.status-stamped  { color:#10b981; }
.status-failed   { color:#ef4444; }
.ticket-img { max-height:420px; object-fit:contain; border-radius:8px; }
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container">
    <a class="navbar-brand" href="/tickets.php"><i class="bi bi-receipt-cutoff me-1"></i>Invoice Tracker</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="/tickets.php"><i class="bi bi-list-ul me-1"></i>Tickets</a></li>
        <li class="nav-item"><a class="nav-link" href="/upload.php"><i class="bi bi-upload me-1"></i>Subir ticket</a></li>
        <li class="nav-item"><a class="nav-link" href="/companies.php"><i class="bi bi-building me-1"></i>Empresas</a></li>
      </ul>
    </div>
  </div>
</nav>
<div class="container pb-5">
<?php }

function layout_foot(): void { ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php }

function status_badge(string $status): string {
    return match($status) {
        'stamped' => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Timbrado</span>',
        'failed'  => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Fallido</span>',
        default   => '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pendiente</span>',
    };
}
