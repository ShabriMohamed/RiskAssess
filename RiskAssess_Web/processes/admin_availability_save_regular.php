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

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$counsellor_id = isset($_POST['counsellor_id']) ? intval($_POST['counsellor_id']) : 0;
$day_of_week = isset($_POST['day_of_week']) ? intval($_POST['day_of_week']) : -1;
$start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
$end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';
$is_available = isset($_POST['is_available']) ? 1 : 0;

// Log the incoming data
$logData = "Received data: " . print_r($_POST, true);
error_log($logData);

// Validate inputs
if (!$counsellor_id || $day_of_week < 0 || $day_of_week > 6 || !$start_time || !$end_time) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

// Validate time format and logic
if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $start_time) || 
    !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $end_time)) {
    echo json_encode(['success' => false, 'message' => 'Invalid time format']);
    exit;
}

if (strtotime($start_time) >= strtotime($end_time)) {
    echo json_encode(['success' => false, 'message' => 'End time must be after start time']);
    exit;
}

try {
    // Check for overlapping schedules
    $stmt = $conn->prepare("
        SELECT id FROM counsellor_availability 
        WHERE counsellor_id = ? AND day_of_week = ? AND id != ? AND 
        ((start_time <= ? AND end_time > ?) OR 
         (start_time < ? AND end_time >= ?) OR 
         (start_time >= ? AND end_time <= ?))
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("iiisssss", $counsellor_id, $day_of_week, $id, $end_time, $start_time, $end_time, $start_time, $start_time, $end_time);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'This time slot overlaps with an existing schedule']);
        exit;
    }
    $stmt->close();

    // Insert or update
    if ($id) {
        // Update existing
        $stmt = $conn->prepare("
            UPDATE counsellor_availability 
            SET day_of_week = ?, start_time = ?, end_time = ?, is_available = ? 
            WHERE id = ? AND counsellor_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare update failed: " . $conn->error);
        }
        
        $stmt->bind_param("issiii", $day_of_week, $start_time, $end_time, $is_available, $id, $counsellor_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0 || $stmt->errno == 0) {
            echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating schedule: ' . $stmt->error]);
        }
    } else {
        // Insert new
        $stmt = $conn->prepare("
            INSERT INTO counsellor_availability (counsellor_id, day_of_week, start_time, end_time, is_available) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare insert failed: " . $conn->error);
        }
        
        $stmt->bind_param("iissi", $counsellor_id, $day_of_week, $start_time, $end_time, $is_available);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Schedule added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding schedule: ' . $stmt->error]);
        }
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error in save_regular: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
