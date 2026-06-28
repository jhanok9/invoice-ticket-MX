<?php
ini_set('display_errors', '0');
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

$ticketId   = (int)($_GET['ticket_id'] ?? 0);
$resultFile = sys_get_temp_dir() . '/stamp_result_' . $ticketId . '.json';

if (!$ticketId) {
    echo json_encode(['done' => false]); exit;
}

// The shell redirect (>) creates the result file immediately (empty).
// Keep polling until Node actually writes content, or until timeout (5 min).
clearstatcache();
$fileReady = file_exists($resultFile) && filesize($resultFile) > 0;

if (!$fileReady) {
    // Timeout: if the file has existed for more than 5 minutes still empty, give up
    $timedOut = file_exists($resultFile) && (time() - filemtime($resultFile)) > 300;
    if ($timedOut) {
        @unlink($resultFile);
        $msg = 'El proceso de automatización tardó demasiado (>5 min). Revisa si Chrome está abierto.';
        db()->prepare('INSERT INTO stamp_logs (ticket_id,result,message) VALUES (?,?,?)')->execute([$ticketId,'error',$msg]);
        db()->prepare("UPDATE tickets SET status='failed' WHERE id=?")->execute([$ticketId]);
        echo json_encode(['done' => true, 'success' => false, 'message' => $msg]);
    } else {
        echo json_encode(['done' => false, 'message' => 'El navegador está procesando el formulario...']);
    }
    exit;
}

$output = file_get_contents($resultFile);
// Extract the last line that looks like JSON (ignores any Playwright stderr warnings mixed in)
$jsonLine = null;
foreach (array_reverse(explode("\n", trim($output))) as $line) {
    $line = trim($line);
    if ($line !== '' && $line[0] === '{') { $jsonLine = $line; break; }
}
$result = $jsonLine ? json_decode($jsonLine, true) : null;

if (!$result) {
    @unlink($resultFile);
    $msg = 'El script no retornó JSON válido: ' . substr($output ?? '', 0, 300);
    db()->prepare('INSERT INTO stamp_logs (ticket_id,result,message) VALUES (?,?,?)')->execute([$ticketId,'error',$msg]);
    db()->prepare("UPDATE tickets SET status='failed' WHERE id=?")->execute([$ticketId]);
    echo json_encode(['done' => true, 'success' => false, 'message' => $msg]);
    exit;
}

@unlink($resultFile);

$success    = (bool)($result['success'] ?? false);
$message    = $result['message']    ?? ($success ? 'Timbrado exitosamente' : 'Error desconocido');
$screenshot = $result['screenshot'] ?? null;
$xmlPath    = $result['xml_path']   ?? null;
$pdfPath    = $result['pdf_path']   ?? null;

db()->prepare('INSERT INTO stamp_logs (ticket_id,result,message,screenshot) VALUES (?,?,?,?)')
    ->execute([$ticketId, $success ? 'success' : 'error', $message, $screenshot]);

if ($success) {
    db()->prepare("UPDATE tickets SET status='stamped', stamped_at=NOW(),
                   xml_path=COALESCE(:xml, xml_path), pdf_path=COALESCE(:pdf, pdf_path) WHERE id=:id")
        ->execute([':xml' => $xmlPath, ':pdf' => $pdfPath, ':id' => $ticketId]);
} else {
    db()->prepare("UPDATE tickets SET status='failed' WHERE id=?")->execute([$ticketId]);
}

echo json_encode(['done' => true, 'success' => $success, 'message' => $message, 'screenshot' => $screenshot]);
