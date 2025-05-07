<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit;
}

// Get appointment details for audit log
$stmt = $conn->prepare("
    SELECT a.counsellor_id, a.client_id, a.appointment_date, a.start_time, a.end_time, a.status,
           c.name as client_name, co.name as counsellor_name
    FROM appointments a
    JOIN users c ON a.client_id = c.id
    JOIN counsellors cou ON a.counsellor_id = cou.id
    JOIN users co ON cou.user_id = co.id
    WHERE a.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
    exit;
}

$appointment = $result->fetch_assoc();
$stmt->close();

// Check if appointment is in the past
$appointmentDateTime = strtotime($appointment['appointment_date'] . ' ' . $appointment['start_time']);
if ($appointmentDateTime < time() && $appointment['status'] != 'cancelled') {
    echo json_encode(['success' => false, 'message' => 'Cannot delete past appointments that were not cancelled']);
    exit;
}

// Delete appointment
$stmt = $conn->prepare("DELETE FROM appointments WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    // Log the action
    $admin_id = $_SESSION['user_id'];
    $action = "Deleted appointment #$id";
    $details = json_encode([
        'client' => $appointment['client_name'],
        'counsellor' => $appointment['counsellor_name'],
        'date' => $appointment['appointment_date'],
        'time' => $appointment['start_time'] . ' - ' . $appointment['end_time'],
        'status' => $appointment['status']
    ]);
    
    $logStmt = $conn->prepare("
        INSERT INTO audit_log (user_id, action, table_name, record_id, details) 
        VALUES (?, ?, 'appointments', ?, ?)
    ");
    $logStmt->bind_param("isis", $admin_id, $action, $id, $details);
    $logStmt->execute();
    $logStmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Appointment deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error deleting appointment: ' . $stmt->error]);
}
$stmt->close();
