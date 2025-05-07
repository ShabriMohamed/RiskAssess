<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$counsellor_id = isset($_POST['counsellor_id']) ? intval($_POST['counsellor_id']) : 0;
$client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
$appointment_date = isset($_POST['appointment_date']) ? $_POST['appointment_date'] : '';
$start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
$end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';
$notes = isset($_POST['notes']) ? $_POST['notes'] : '';
$status = 'scheduled';

// Validate inputs
if (!$counsellor_id || !$client_id || !$appointment_date || !$start_time || !$end_time) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $start_time) || 
    !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $end_time)) {
    echo json_encode(['success' => false, 'message' => 'Invalid time format']);
    exit;
}

if (strtotime($start_time) >= strtotime($end_time)) {
    echo json_encode(['success' => false, 'message' => 'End time must be after start time']);
    exit;
}

// Check if date is in the past
if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
    echo json_encode(['success' => false, 'message' => 'Cannot schedule appointments in the past']);
    exit;
}

// Check if counsellor is available on this date (regular schedule or exception)
$dayOfWeek = date('w', strtotime($appointment_date));

// First check for exceptions
$stmt = $conn->prepare("
    SELECT is_available FROM availability_exceptions 
    WHERE counsellor_id = ? AND exception_date = ?
");
$stmt->bind_param("is", $counsellor_id, $appointment_date);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $exception = $result->fetch_assoc();
    if ($exception['is_available'] != 1) {
        echo json_encode(['success' => false, 'message' => 'Counsellor is not available on this date (marked as exception)']);
        exit;
    }
} else {
    // Check regular schedule
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM counsellor_availability 
        WHERE counsellor_id = ? AND day_of_week = ? AND is_available = 1 
        AND start_time <= ? AND end_time >= ?
    ");
    $stmt->bind_param("isss", $counsellor_id, $dayOfWeek, $start_time, $end_time);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        echo json_encode(['success' => false, 'message' => 'Selected time is outside counsellor\'s available hours']);
        exit;
    }
}
$stmt->close();

// Check for overlapping appointments
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM appointments 
    WHERE counsellor_id = ? AND appointment_date = ? AND status != 'cancelled'
    AND ((start_time <= ? AND end_time > ?) OR 
         (start_time < ? AND end_time >= ?) OR 
         (start_time >= ? AND end_time <= ?))
");
$stmt->bind_param("isssssss", $counsellor_id, $appointment_date, $end_time, $start_time, $end_time, $start_time, $start_time, $end_time);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['count'] > 0) {
    echo json_encode(['success' => false, 'message' => 'This time slot is already booked']);
    exit;
}
$stmt->close();

// Insert appointment
$stmt = $conn->prepare("
    INSERT INTO appointments (counsellor_id, client_id, appointment_date, start_time, end_time, status, notes) 
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("iisssss", $counsellor_id, $client_id, $appointment_date, $start_time, $end_time, $status, $notes);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $appointmentId = $stmt->insert_id;
    
    // Log the action
    $admin_id = $_SESSION['user_id'];
    $action = "Created appointment #$appointmentId";
    $details = json_encode([
        'counsellor_id' => $counsellor_id,
        'client_id' => $client_id,
        'appointment_date' => $appointment_date,
        'start_time' => $start_time,
        'end_time' => $end_time
    ]);
    
    $logStmt = $conn->prepare("
        INSERT INTO audit_log (user_id, action, table_name, record_id, details) 
        VALUES (?, ?, 'appointments', ?, ?)
    ");
    $logStmt->bind_param("isis", $admin_id, $action, $appointmentId, $details);
    $logStmt->execute();
    $logStmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Appointment scheduled successfully', 'id' => $appointmentId]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error scheduling appointment: ' . $stmt->error]);
}
$stmt->close();
