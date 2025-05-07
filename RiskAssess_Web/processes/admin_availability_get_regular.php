<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$counsellor_id = isset($_GET['counsellor_id']) ? intval($_GET['counsellor_id']) : 0;

if (!$counsellor_id) {
    echo json_encode([]);
    exit;
}

$schedules = [];

try {
    $stmt = $conn->prepare("
        SELECT id, counsellor_id, day_of_week, start_time, end_time, is_available 
        FROM counsellor_availability 
        WHERE counsellor_id = ?
        ORDER BY day_of_week, start_time
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $counsellor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }

    $stmt->close();
    
    echo json_encode($schedules);
} catch (Exception $e) {
    error_log("Error in get_regular: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
