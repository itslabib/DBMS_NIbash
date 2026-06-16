<?php
require_once '../includes/db_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

$scanned_plate = mysqli_real_escape_string($conn, $_POST['license_plate'] ?? '');

if (empty($scanned_plate)) {
    echo json_encode(['status' => 'error', 'message' => 'No license plate provided.']);
    exit();
}

// Clean the scanned plate by removing spaces, hyphens, and converting to lowercase
$clean_scanned = strtolower(str_replace([' ', '-'], '', $scanned_plate));

// Search for the plate in parking_slots
// We use a reverse LIKE query: checking if the clean database plate exists WITHIN the clean scanned text.
// This ensures that if the OCR reads "Title DHK 12-1234", it will still perfectly match "DHK 12-1234".
$query = "SELECT id, slot_number, current_status, apt_id, temporary_until 
          FROM parking_slots 
          WHERE (license_plate IS NOT NULL AND license_plate != '' AND '$clean_scanned' LIKE CONCAT('%', LOWER(REPLACE(REPLACE(license_plate, ' ', ''), '-', '')), '%'))
          OR (temporary_plate IS NOT NULL AND temporary_plate != '' AND '$clean_scanned' LIKE CONCAT('%', LOWER(REPLACE(REPLACE(temporary_plate, ' ', ''), '-', '')), '%') AND ((temporary_until IS NOT NULL AND temporary_until > NOW()) OR current_status = 'Occupied'))
          LIMIT 1";

$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $slot = mysqli_fetch_assoc($result);
    $slot_id = $slot['id'];
    $slot_number = $slot['slot_number'];
    $current_status = $slot['current_status'];
    
    // Toggle Status
    $new_status = ($current_status === 'Occupied') ? 'Vacant' : 'Occupied';
    
    $update_q = "UPDATE parking_slots SET current_status = '$new_status' WHERE id = '$slot_id'";
    
    if (mysqli_query($conn, $update_q)) {
        // Record log (optional but good practice)
        $action_msg = ($new_status === 'Occupied') ? 'Entry Logged' : 'Exit Logged';
        
        echo json_encode([
            'status' => 'success', 
            'message' => "Access Granted: $action_msg. Spot $slot_number is now $new_status."
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Database error during status update.'
        ]);
    }
} else {
    // No match found
    echo json_encode([
        'status' => 'error', 
        'message' => 'Number plate not found. Please contact the person you are visiting.'
    ]);
}
?>
