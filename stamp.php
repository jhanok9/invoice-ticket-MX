<?php
ini_set('display_errors', '0');
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit;
}

$body     = json_decode(file_get_contents('php://input'), true);
$ticketId = (int)($body['ticket_id'] ?? 0);
if (!$ticketId) {
    http_response_code(400); echo json_encode(['success'=>false,'message'=>'ticket_id required']); exit;
}

// ── Poll mode: frontend asks for status while script runs in background ──
if (($body['action'] ?? '') === 'poll') {
    $row = db()->prepare("SELECT status FROM tickets WHERE id=?")->execute([$ticketId]);
    $log = db()->prepare("SELECT result,message,screenshot FROM stamp_logs WHERE ticket_id=? ORDER BY attempted_at DESC LIMIT 1")
               ->execute([$ticketId]);
    $log = db()->query("SELECT result,message,screenshot FROM stamp_logs WHERE ticket_id={$ticketId} ORDER BY attempted_at DESC LIMIT 1")->fetch();
    $tk  = db()->query("SELECT status FROM tickets WHERE id={$ticketId}")->fetch();
    echo json_encode(['status' => $tk['status'], 'log' => $log]);
    exit;
}

$stmt = db()->prepare('
    SELECT t.*, c.name AS company_name, c.rfc AS company_rfc,
           c.email AS company_email, c.zip_code, c.tax_regime
    FROM tickets t
    LEFT JOIN companies c ON c.id = t.company_id
    WHERE t.id = ?
');
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    echo json_encode(['success'=>false,'message'=>'Ticket no encontrado']); exit;
}

// Map store name → script filename
$scriptMap = [
    'fougasse'                       => 'stamp_fougasse.js',
    'panaderias artesanales fougasse'=> 'stamp_fougasse.js',
];

$storeLower = strtolower($ticket['store_name'] ?? '');
$scriptFile = null;
foreach ($scriptMap as $keyword => $file) {
    if (str_contains($storeLower, $keyword)) {
        $scriptFile = SCRIPTS_DIR . $file;
        break;
    }
}

if (!$scriptFile || !file_exists($scriptFile)) {
    $msg = "No hay script de timbrado para '{$ticket['store_name']}'.";
    db()->prepare('INSERT INTO stamp_logs (ticket_id,result,message) VALUES (?,?,?)')->execute([$ticketId,'error',$msg]);
    echo json_encode(['success'=>false,'message'=>$msg]);
    exit;
}

$payload = json_encode([
    'ticket_id'     => $ticket['id'],
    'ticket_number' => $ticket['ticket_number'],
    'serie'         => $ticket['serie'],
    'total'         => $ticket['total'],
    'purchase_date' => $ticket['purchase_date'],
    'company_rfc'   => $ticket['company_rfc'],
    'company_email' => $ticket['company_email'],
    'zip_code'      => $ticket['zip_code'],
    'tax_regime'    => $ticket['tax_regime'],
    'screenshot_dir'=> UPLOADS_DIR,
    'screenshot_url'=> UPLOADS_URL,
]);

// Result file for background communication
$resultFile = sys_get_temp_dir() . "/stamp_result_{$ticketId}.json";
@unlink($resultFile);

// We need Node to run in the background so PHP can return immediately.
// Trick: run /bin/sh with the command backgrounded using &
// proc_close() waits for the SHELL to exit (instant), not for Node.
// Node keeps running as an orphan process writing its result to $resultFile.
$shellCmd = sprintf(
    '%s %s %s > %s 2>&1 &',
    NODE_BIN,
    escapeshellarg($scriptFile),
    escapeshellarg($payload),
    escapeshellarg($resultFile)
);

$descriptors = [
    0 => ['file', '/dev/null', 'r'],
    1 => ['file', '/dev/null', 'w'],
    2 => ['file', '/dev/null', 'w'],
];
$process = proc_open('/bin/sh -c ' . escapeshellarg($shellCmd), $descriptors, $pipes);

if (!is_resource($process)) {
    $msg = 'No se pudo lanzar el proceso de automatización.';
    db()->prepare('INSERT INTO stamp_logs (ticket_id,result,message) VALUES (?,?,?)')->execute([$ticketId,'error',$msg]);
    echo json_encode(['success'=>false,'message'=>$msg]);
    exit;
}

// Shell exits immediately (it backgrounded Node); this returns right away
proc_close($process);

// Tell the browser to start polling
echo json_encode([
    'success' => true,
    'polling' => true,
    'message' => 'Automatización iniciada. El navegador está completando el formulario...',
]);
exit;
