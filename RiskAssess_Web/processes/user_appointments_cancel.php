<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$appointment_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

// Validate appointment ID
if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit;
}

// Check if the appointment exists and belongs to the user
$stmt = $conn->prepare("SELECT a.id, a.appointment_date, a.start_time, a.status FROM appointments a WHERE a.id = ? AND a.client_id = ?");
$stmt->bind_param("ii", $appointment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found or unauthorized access']);
    $stmt->close();
    exit;
}

$appointment = $result->fetch_assoc();
$stmt->close();

// Check if the appointment is already cancelled
if ($appointment['status'] === 'cancelled') {
    echo json_encode(['success' => false, 'message' => 'This appointment is already cancelled']);
    exit;
}

// Check if the appointment is in the past
$appointmentDateTime = strtotime($appointment['appointment_date'] . ' ' . $appointment['start_time']);
if ($appointmentDateTime < time()) {
    echo json_encode(['success' => false, 'message' => 'Cannot cancel past appointments']);
    exit;
}

// Check for late cancellation (less than 24 hours before)
$cancellationDeadline = $appointmentDateTime - (24 * 60 * 60); // 24 hours before
$isLateCancellation = time() > $cancellationDeadline;

// Prepare cancellation note
$cancellationNote = $reason ? $reason : 'No reason provided';

// Update appointment status to 'cancelled'
$stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled', notes = CONCAT(IFNULL(notes, ''), '\n\nCancellation reason: ', ?) WHERE id = ? AND client_id = ?");
$stmt->bind_param("sii", $cancellationNote, $appointment_id, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    // Log the cancellation
    $action = "Cancelled appointment #$appointment_id";
    $details = json_encode([
        'appointment_id' => $appointment_id,
        'reason' => $reason,
        'is_late_cancellation' => $isLateCancellation
    ]);
    
    $logStmt = $conn->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, details) VALUES (?, ?, 'appointments', ?, ?)");
    $logStmt->bind_param("isis", $user_id, $action, $appointment_id, $details);
    $logStmt->execute();
    $logStmt->close();

    // Set cancellation message
    $message = 'Appointment cancelled successfully.';
    if ($isLateCancellation) {
        $message .= ' Please note that late cancellations may be subject to a cancellation fee.';
    }

    echo json_encode(['success' => true, 'message' => $message]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error cancelling appointment: ' . $stmt->error]);
}

$stmt->close();
?>
