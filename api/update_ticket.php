<?php
ini_set('display_errors', '0');
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$id   = (int)($body['id'] ?? 0);
if (!$id) {
    http_response_code(400); echo json_encode(['error' => 'id required']); exit;
}

$fields = ['store_name','store_rfc','ticket_number','serie','purchase_date',
           'subtotal','tax','total','company_id','notes','status'];

$sets   = [];
$params = [':id' => $id];

foreach ($fields as $f) {
    if (!array_key_exists($f, $body)) continue;
    $val = $body[$f];
    // Coerce numeric fields
    if (in_array($f, ['subtotal','tax','total'])) $val = $val !== '' ? (float)$val : null;
    if ($f === 'company_id')                       $val = $val !== '' ? (int)$val  : null;
    if ($val === '')                               $val = null;
    $sets[]       = "{$f} = :{$f}";
    $params[":{$f}"] = $val;
}

if (empty($sets)) {
    echo json_encode(['error' => 'Nothing to update']); exit;
}

// Reset stamped_at if status manually changed away from stamped
if (isset($body['status']) && $body['status'] !== 'stamped') {
    $sets[] = 'stamped_at = NULL';
}

db()->prepare('UPDATE tickets SET ' . implode(', ', $sets) . ' WHERE id = :id')
    ->execute($params);

echo json_encode(['success' => true]);
