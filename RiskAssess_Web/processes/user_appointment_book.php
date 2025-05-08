<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$counselor_id = isset($_POST['counselor_id']) ? intval($_POST['counselor_id']) : 0;
$appointment_date = isset($_POST['appointment_date']) ? $_POST['appointment_date'] : '';
$start_time = isset($_POST['start_time']) ? $_POST['start_time'] : '';
$end_time = isset($_POST['end_time']) ? $_POST['end_time'] : '';
$notes = isset($_POST['notes']) ? $_POST['notes'] : '';
$status = 'scheduled';

// Validate inputs
if (!$counselor_id || !$appointment_date || !$start_time || !$end_time) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

// Check if date is in the past
if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
    echo json_encode(['success' => false, 'message' => 'Cannot schedule appointments in the past']);
    exit;
}

// Check if counselor is available on this date (regular schedule or exception)
$dayOfWeek = date('w', strtotime($appointment_date));

// First check for exceptions
$stmt = $conn->prepare("
    SELECT is_available FROM availability_exceptions 
    WHERE counsellor_id = ? AND exception_date = ?
");
$stmt->bind_param("is", $counselor_id, $appointment_date);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $exception = $result->fetch_assoc();
    if ($exception['is_available'] != 1) {
        echo json_encode(['success' => false, 'message' => 'Counselor is not available on this date']);
        exit;
    }
} else {
    // Check regular schedule
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM counsellor_availability 
        WHERE counsellor_id = ? AND day_of_week = ? AND is_available = 1 
        AND start_time <= ? AND end_time >= ?
    ");
    $stmt->bind_param("isss", $counselor_id, $dayOfWeek, $start_time, $end_time);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        echo json_encode(['success' => false, 'message' => 'Selected time is outside counselor\'s available hours']);
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
$stmt->bind_param("isssssss", $counselor_id, $appointment_date, $end_time, $start_time, $end_time, $start_time, $start_time, $end_time);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['count'] > 0) {
    echo json_encode(['success' => false, 'message' => 'This time slot is already booked']);
    exit;
}
$stmt->close();

// Check if user already has an appointment at this time
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM appointments 
    WHERE client_id = ? AND appointment_date = ? AND status != 'cancelled'
    AND ((start_time <= ? AND end_time > ?) OR 
         (start_time < ? AND end_time >= ?) OR 
         (start_time >= ? AND end_time <= ?))
");
$stmt->bind_param("isssssss", $user_id, $appointment_date, $end_time, $start_time, $end_time, $start_time, $start_time, $end_time);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['count'] > 0) {
    echo json_encode(['success' => false, 'message' => 'You already have an appointment scheduled at this time']);
    exit;
}
$stmt->close();

// Insert appointment
$stmt = $conn->prepare("
    INSERT INTO appointments (counsellor_id, client_id, appointment_date, start_time, end_time, status, notes) 
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("iisssss", $counselor_id, $user_id, $appointment_date, $start_time, $end_time, $status, $notes);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $appointment_id = $stmt->insert_id;
    
    // Log the action
    $action = "Booked appointment #$appointment_id";
    $details = json_encode([
        'counsellor_id' => $counselor_id,
        'appointment_date' => $appointment_date,
        'start_time' => $start_time,
        'end_time' => $end_time
    ]);
    
    $logStmt = $conn->prepare("
        INSERT INTO audit_log (user_id, action, table_name, record_id, details) 
        VALUES (?, ?, 'appointments', ?, ?)
    ");
    $logStmt->bind_param("isis", $user_id, $action, $appointment_id, $details);
    $logStmt->execute();
    $logStmt->close();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Appointment booked successfully', 
        'appointment_id' => $appointment_id
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error booking appointment: ' . $stmt->error]);
}
$stmt->close();
