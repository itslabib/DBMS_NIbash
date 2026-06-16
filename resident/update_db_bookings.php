<?php
require_once '../includes/db_config.php';

// Add availability_time column if it doesn't exist
$check_column = mysqli_query($conn, "SHOW COLUMNS FROM service_providers LIKE 'availability_time'");
if (mysqli_num_rows($check_column) == 0) {
    mysqli_query($conn, "ALTER TABLE service_providers ADD COLUMN availability_time VARCHAR(255) DEFAULT '10:00 AM - 05:00 PM'");
    echo "Added availability_time column to service_providers.\n";
}

// Create provider_bookings table
$create_bookings_table = "
CREATE TABLE IF NOT EXISTS provider_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    resident_id INT NOT NULL,
    booking_date DATE NOT NULL,
    time_slot VARCHAR(100) NOT NULL,
    status ENUM('Booked', 'Completed', 'Cancelled') DEFAULT 'Booked',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES users(id) ON DELETE CASCADE
)";

if (mysqli_query($conn, $create_bookings_table)) {
    echo "provider_bookings table created or already exists.\n";
} else {
    echo "Error creating provider_bookings table: " . mysqli_error($conn) . "\n";
}

echo "Database updates complete.\n";
?>
