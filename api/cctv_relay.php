<?php
session_start();
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

$camera_id = $_POST['camera_id'] ?? '';
$building_id = $_POST['building_id'] ?? '';
$image_data = $_POST['image'] ?? '';

if (!$camera_id || !$building_id || !$image_data) {
    http_response_code(400);
    exit(json_encode(['status' => 'error', 'message' => 'Missing data']));
}

$stream_dir = "../assets/cctv/streams/{$building_id}";
if (!is_dir($stream_dir)) {
    mkdir($stream_dir, 0777, true);
}

$image_path = "{$stream_dir}/cam_{$camera_id}.jpg";

$image_parts = explode(";base64,", $image_data);
if (count($image_parts) == 2) {
    $image_base64 = base64_decode($image_parts[1]);
    // Save to temp stream file
    file_put_contents($image_path, $image_base64);
}

echo json_encode(['status' => 'success']);
