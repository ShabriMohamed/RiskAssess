<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the incoming data
$logData = "Received data: " . print_r($_POST, true);
error_log($logData);

$counsellor_id = isset($_POST['counsellor_id']) ? intval($_POST['counsellor_id']) : 0;
$days = isset($_POST['days']) ? $_POST['days'] : [];
$start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
$end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';
$is_available = isset($_POST['is_available']) ? intval($_POST['is_available']) : 1;

// Validate inputs
if (!$counsellor_id) {
    echo json_encode(['success' => false, 'message' => 'Missing counsellor ID']);
    exit;
}

if (empty($days)) {
    echo json_encode(['success' => false, 'message' => 'No days selected']);
    exit;
}

if (!$start_time || !$end_time) {
    echo json_encode(['success' => false, 'message' => 'Missing start or end time']);
    exit;
}

// Validate time format and logic
if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $start_time) || 
    !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $end_time)) {
    echo json_encode(['success' => false, 'message' => 'Invalid time format. Use HH:MM format.']);
    exit;
}

if (strtotime($start_time) >= strtotime($end_time)) {
    echo json_encode(['success' => false, 'message' => 'End time must be after start time']);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    // First delete existing schedules for the selected days
    foreach ($days as $day) {
        $stmt = $conn->prepare("DELETE FROM counsellor_availability WHERE counsellor_id = ? AND day_of_week = ?");
        if (!$stmt) {
            throw new Exception("Prepare delete failed: " . $conn->error);
        }
        
        $stmt->bind_param("ii", $counsellor_id, $day);
        if (!$stmt->execute()) {
            throw new Exception("Execute delete failed: " . $stmt->error);
        }
        
        $stmt->close();
    }
    
    // Now insert new schedules
    foreach ($days as $day) {
        $stmt = $conn->prepare("INSERT INTO counsellor_availability (counsellor_id, day_of_week, start_time, end_time, is_available) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare insert failed: " . $conn->error);
        }
        
        $stmt->bind_param("iissi", $counsellor_id, $day, $start_time, $end_time, $is_available);
        if (!$stmt->execute()) {
            throw new Exception("Execute insert failed: " . $stmt->error);
        }
        
        $stmt->close();
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Bulk schedule applied successfully']);
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    error_log("Error in bulk scheduling: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
