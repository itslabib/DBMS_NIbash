<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

// Read the raw POST data (contains the base64 image in JSON format: {"image": "..."})
$inputJSON = file_get_contents('php://input');

// Send the exact same JSON to the FastAPI server running locally
$ch = curl_init('http://127.0.0.1:8000/scan');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $inputJSON);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($inputJSON)
]);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpcode !== 200) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to connect to FastAPI OCR server. Please make sure you have started "start_ocr_server.bat" and keep its window open.'
    ]);
    exit();
}

// Return the exact JSON response from the FastAPI server directly to the frontend
echo $response;
?>
