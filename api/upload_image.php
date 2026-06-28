<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No image received']);
    exit;
}

$file = $_FILES['image'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Upload error: ' . $file['error']]);
    exit;
}

$allowed = ['image/jpeg','image/png','image/webp','image/heic','image/heif'];
$mime    = mime_content_type($file['tmp_name']);
if (!in_array($mime, $allowed)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de archivo no soportado: ' . $mime]);
    exit;
}

$ext      = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
$filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest     = UPLOADS_DIR . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo guardar el archivo']);
    exit;
}

echo json_encode(['path' => UPLOADS_URL . $filename, 'filename' => $filename]);
