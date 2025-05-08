<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$appointment_id) {
    echo json_encode(['error' => 'Invalid appointment ID']);
    exit;
}

$stmt = $conn->prepare("
    SELECT a.id, a.counsellor_id, a.appointment_date, a.start_time, a.end_time, a.status, a.notes,
           c.id as counsellor_id, u.name as counsellor_name, c.profile_photo
    FROM appointments a
    JOIN counsellors c ON a.counsellor_id = c.id
    JOIN users u ON c.user_id = u.id
    WHERE a.id = ? AND a.client_id = ?
");
$stmt->bind_param("ii", $appointment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Appointment not found or unauthorized access']);
    exit;
}

$appointment = $result->fetch_assoc();
$stmt->close();

echo json_encode($appointment);
