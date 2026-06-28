<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$ticketId = (int)($_POST['ticket_id'] ?? 0);
if (!$ticketId) {
    http_response_code(400); echo json_encode(['error' => 'ticket_id required']); exit;
}

$updates = [];
$params  = [];

foreach (['xml' => '.xml', 'pdf' => '.pdf'] as $type => $expectedExt) {
    if (empty($_FILES[$type]) || $_FILES[$type]['error'] === UPLOAD_ERR_NO_FILE) continue;

    $file = $_FILES[$type];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => "Upload error for {$type}: " . $file['error']]); exit;
    }

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = "cfdi_{$ticketId}_{$type}_" . date('Ymd_His') . ".{$ext}";
    $dest     = UPLOADS_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['error' => "Could not save {$type} file"]); exit;
    }

    $updates[] = "{$type}_path = :{$type}_path";
    $params[":{$type}_path"] = UPLOADS_URL . $filename;
}

if (empty($updates)) {
    echo json_encode(['error' => 'No files uploaded']); exit;
}

$params[':id'] = $ticketId;

// Only promote to stamped if not already stamped
$current = db()->prepare("SELECT status FROM tickets WHERE id = ?")->execute([$ticketId]);
$current = db()->query("SELECT status FROM tickets WHERE id = {$ticketId}")->fetchColumn();

if ($current !== 'stamped') {
    $updates[] = "status = 'stamped'";
    $updates[] = "stamped_at = NOW()";
    $logMsg = 'Archivos CFDI adjuntados manualmente — marcado como timbrado.';
} else {
    $logMsg = 'Archivos CFDI reemplazados manualmente.';
}

db()->prepare('UPDATE tickets SET ' . implode(', ', $updates) . ' WHERE id = :id')
    ->execute($params);

db()->prepare("INSERT INTO stamp_logs (ticket_id, result, message) VALUES (?, 'success', ?)")
    ->execute([$ticketId, $logMsg]);

echo json_encode(['success' => true, 'status' => 'stamped']);
