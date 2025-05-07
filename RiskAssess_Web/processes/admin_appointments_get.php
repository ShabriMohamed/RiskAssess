<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    echo json_encode(['error' => 'Invalid appointment ID']);
    exit;
}

$stmt = $conn->prepare("
    SELECT a.id, a.counsellor_id, a.client_id, a.appointment_date, a.start_time, a.end_time, a.status, a.notes, a.created_at,
           c.user_id as counsellor_user_id, cu.name as counsellor_name,
           cl.name as client_name
    FROM appointments a
    JOIN counsellors c ON a.counsellor_id = c.id
    JOIN users cu ON c.user_id = cu.id
    JOIN users cl ON a.client_id = cl.id
    WHERE a.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Appointment not found']);
    exit;
}

$appointment = $result->fetch_assoc();

// Format date for display
$date = new DateTime($appointment['appointment_date']);
$formattedDate = $date->format('M d, Y');

$response = [
    'id' => $appointment['id'],
    'client_id' => $appointment['client_id'],
    'client_name' => $appointment['client_name'],
    'counsellor_id' => $appointment['counsellor_id'],
    'counsellor_name' => $appointment['counsellor_name'],
    'appointment_date' => $formattedDate,
    'raw_date' => $appointment['appointment_date'],
    'start_time' => $appointment['start_time'],
    'end_time' => $appointment['end_time'],
    'status' => $appointment['status'],
    'notes' => $appointment['notes'],
    'created_at' => date('M d, Y', strtotime($appointment['created_at']))
];

$stmt->close();

echo json_encode($response);
