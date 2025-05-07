<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';
$notes = isset($_POST['notes']) ? $_POST['notes'] : '';

// Validate inputs
if (!$id || !in_array($status, ['scheduled', 'completed', 'cancelled', 'no-show'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

// Get current appointment data for audit log
$stmt = $conn->prepare("
    SELECT counsellor_id, client_id, appointment_date, start_time, end_time, status, notes
    FROM appointments WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
    exit;
}

$oldData = $result->fetch_assoc();
$stmt->close();

// Update appointment
$stmt = $conn->prepare("UPDATE appointments SET status = ?, notes = ? WHERE id = ?");
$stmt->bind_param("ssi", $status, $notes, $id);
$stmt->execute();

if ($stmt->affected_rows > 0 || $stmt->errno == 0) {
    // Log the action
    $admin_id = $_SESSION['user_id'];
    $action = "Updated appointment #$id status to $status";
    $details = json_encode([
        'old_status' => $oldData['status'],
        'new_status' => $status,
        'notes' => $notes
    ]);
    
    $logStmt = $conn->prepare("
        INSERT INTO audit_log (user_id, action, table_name, record_id, details) 
        VALUES (?, ?, 'appointments', ?, ?)
    ");
    $logStmt->bind_param("isis", $admin_id, $action, $id, $details);
    $logStmt->execute();
    $logStmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Appointment updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error updating appointment: ' . $stmt->error]);
}
$stmt->close();
