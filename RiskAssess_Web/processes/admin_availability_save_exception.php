<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$counsellor_id = isset($_POST['counsellor_id']) ? intval($_POST['counsellor_id']) : 0;
$exception_date = isset($_POST['exception_date']) ? $_POST['exception_date'] : '';
$is_available = isset($_POST['is_available']) ? 1 : 0;
$start_time = isset($_POST['start_time']) ? $_POST['start_time'] : null;
$end_time = isset($_POST['end_time']) ? $_POST['end_time'] : null;
$reason = isset($_POST['reason']) ? $_POST['reason'] : '';

// Validate inputs
if (!$counsellor_id || !$exception_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $exception_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

// If available, validate times
if ($is_available) {
    if (!$start_time || !$end_time) {
        echo json_encode(['success' => false, 'message' => 'Start and end times are required when available']);
        exit;
    }
    
    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $start_time) || 
        !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $end_time)) {
        echo json_encode(['success' => false, 'message' => 'Invalid time format']);
        exit;
    }
    
    if (strtotime($start_time) >= strtotime($end_time)) {
        echo json_encode(['success' => false, 'message' => 'End time must be after start time']);
        exit;
    }
}

// Check for existing appointments if making unavailable
if (!$is_available) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM appointments 
        WHERE counsellor_id = ? AND appointment_date = ? AND status = 'scheduled'
    ");
    $stmt->bind_param("is", $counsellor_id, $exception_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot mark as unavailable: there are scheduled appointments on this date']);
        exit;
    }
    $stmt->close();
}

// Insert or update
if ($id) {
    // Update existing
    if ($is_available) {
        $stmt = $conn->prepare("
            UPDATE availability_exceptions 
            SET exception_date = ?, is_available = ?, start_time = ?, end_time = ?, reason = ? 
            WHERE id = ? AND counsellor_id = ?
        ");
        $stmt->bind_param("sisssii", $exception_date, $is_available, $start_time, $end_time, $reason, $id, $counsellor_id);
    } else {
        $stmt = $conn->prepare("
            UPDATE availability_exceptions 
            SET exception_date = ?, is_available = ?, start_time = NULL, end_time = NULL, reason = ? 
            WHERE id = ? AND counsellor_id = ?
        ");
        $stmt->bind_param("sisii", $exception_date, $is_available, $reason, $id, $counsellor_id);
    }
    $stmt->execute();
    
    if ($stmt->affected_rows > 0 || $stmt->errno == 0) {
        echo json_encode(['success' => true, 'message' => 'Exception updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating exception: ' . $stmt->error]);
    }
} else {
    // Check for existing exception on same date
    $stmt = $conn->prepare("
        SELECT id FROM availability_exceptions 
        WHERE counsellor_id = ? AND exception_date = ?
    ");
    $stmt->bind_param("is", $counsellor_id, $exception_date);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'An exception already exists for this date']);
        exit;
    }
    $stmt->close();
    
    // Insert new
    if ($is_available) {
        $stmt = $conn->prepare("
            INSERT INTO availability_exceptions (counsellor_id, exception_date, is_available, start_time, end_time, reason) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isisss", $counsellor_id, $exception_date, $is_available, $start_time, $end_time, $reason);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO availability_exceptions (counsellor_id, exception_date, is_available, reason) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("isis", $counsellor_id, $exception_date, $is_available, $reason);
    }
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Exception added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding exception: ' . $stmt->error]);
    }
}
$stmt->close();
