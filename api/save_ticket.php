<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);

$required = ['store_name', 'purchase_date', 'total'];
foreach ($required as $f) {
    if (empty($body[$f])) {
        http_response_code(400);
        echo json_encode(['error' => "Field '{$f}' is required"]);
        exit;
    }
}

$stmt = db()->prepare('
    INSERT INTO tickets
        (company_id, image_path, store_name, store_rfc, ticket_number, serie,
         purchase_date, subtotal, tax, total, notes, raw_ai_json)
    VALUES
        (:company_id, :image_path, :store_name, :store_rfc, :ticket_number, :serie,
         :purchase_date, :subtotal, :tax, :total, :notes, :raw_ai_json)
    RETURNING id
');

$stmt->execute([
    'company_id'    => $body['company_id']    ? (int)$body['company_id'] : null,
    'image_path'    => $body['image_path']    ?? null,
    'store_name'    => $body['store_name'],
    'store_rfc'     => $body['store_rfc']     ?? null,
    'ticket_number' => $body['ticket_number'] ?? null,
    'serie'         => $body['serie']         ?? null,
    'purchase_date' => $body['purchase_date'],
    'subtotal'      => $body['subtotal']      ? (float)$body['subtotal'] : null,
    'tax'           => $body['tax']           ? (float)$body['tax']      : null,
    'total'         => (float)$body['total'],
    'notes'         => $body['notes']         ?? null,
    'raw_ai_json'   => isset($body['raw_ai_json']) ? json_encode($body['raw_ai_json']) : null,
]);

$row = $stmt->fetch();
echo json_encode(['id' => $row['id']]);
