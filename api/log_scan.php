<?php
$data = json_decode(file_get_contents('php://input'), true);
if ($data) {
    $log = date('Y-m-d H:i:s') . " - RAW: " . str_replace("\n", " ", $data['raw_text']) . " | CLEAN: " . $data['clean_text'] . "\n";
    file_put_contents('scan_log.txt', $log, FILE_APPEND);
}
?>
