<?php
ini_set('display_errors', '0');
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$body      = json_decode(file_get_contents('php://input'), true);
$imagePath = $body['image_path'] ?? '';

if (!$imagePath) {
    http_response_code(400); echo json_encode(['error' => 'image_path required']); exit;
}

// Resolve local path from URL path
$localPath = __DIR__ . '/..' . $imagePath;
if (!file_exists($localPath)) {
    http_response_code(404); echo json_encode(['error' => 'Image file not found: ' . $localPath]); exit;
}

if (!GEMINI_API_KEY) {
    http_response_code(500); echo json_encode(['error' => 'GEMINI_API_KEY not configured']); exit;
}

$imageData = base64_encode(file_get_contents($localPath));
$mimeType  = mime_content_type($localPath);

$prompt = <<<PROMPT
Analyze this Mexican store receipt (ticket) and extract the following fields as a JSON object.
Return ONLY valid JSON, no markdown, no explanations.

Fields to extract:
- store_name: Business name (razón social) printed at the top
- store_rfc: RFC of the store (format: 3 letters + 6 digits + 3 alphanumeric, e.g. PAF200924E57)
- ticket_number: Ticket/folio number (look for "Ticket:", "Folio:", "No. ticket" etc.)
- serie: Serie code if present (e.g. FOUSAM)
- purchase_date: Date of purchase in YYYY-MM-DD format
- subtotal: Amount before tax as a number (no currency symbol)
- tax: IVA/tax amount as a number
- total: Total amount paid as a number
- facturacion_url: URL for online invoice request if printed on the receipt, otherwise null

Example output:
{"store_name":"PANADERIAS ARTESANALES FOUGASSE","store_rfc":"PAF200924E57","ticket_number":"302000016393","serie":"FOUSAM","purchase_date":"2026-06-25","subtotal":110.34,"tax":17.65,"total":128.00,"facturacion_url":"https://facturacion.fougasse.com.mx/"}
PROMPT;

$payload = [
    'contents' => [[
        'parts' => [
            ['inline_data' => ['mime_type' => $mimeType, 'data' => $imageData]],
            ['text' => $prompt],
        ]
    ]],
    'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 2048],
];

$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
unset($ch);

if ($httpCode !== 200) {
    http_response_code(502);
    $detail = json_decode($response, true);
    $msg = $detail['error']['message'] ?? $response;
    echo json_encode(['error' => "Gemini API error {$httpCode}: {$msg}"]);
    exit;
}

$geminiResponse = json_decode($response, true);
$rawText = $geminiResponse['candidates'][0]['content']['parts'][0]['text'] ?? '';

// Strip markdown code fences if present
$rawText = preg_replace('/^```json\s*|\s*```$/s', '', trim($rawText));

$extracted = json_decode($rawText, true);
if (!$extracted) {
    http_response_code(502);
    echo json_encode(['error' => 'Could not parse Gemini response', 'raw' => $rawText]);
    exit;
}

echo json_encode(['data' => $extracted, 'raw' => $rawText]);
