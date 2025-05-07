<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$query = "
    SELECT a.id, a.counsellor_id, a.client_id, a.appointment_date, a.start_time, a.end_time, a.status, a.notes, a.created_at,
           c.user_id as counsellor_user_id, cu.name as counsellor_name,
           cl.name as client_name
    FROM appointments a
    JOIN counsellors c ON a.counsellor_id = c.id
    JOIN users cu ON c.user_id = cu.id
    JOIN users cl ON a.client_id = cl.id
    ORDER BY a.appointment_date DESC, a.start_time DESC
";

$result = $conn->query($query);
$appointments = [];

while ($row = $result->fetch_assoc()) {
    // Format date and time for display
    $date = new DateTime($row['appointment_date']);
    $formattedDate = $date->format('M d, Y');
    
    $appointments[] = [
        'id' => $row['id'],
        'client_id' => $row['client_id'],
        'client_name' => $row['client_name'],
        'counsellor_id' => $row['counsellor_id'],
        'counsellor_name' => $row['counsellor_name'],
        'appointment_date' => $formattedDate,
        'appointment_time' => $row['start_time'] . ' - ' . $row['end_time'],
        'start_time' => $row['start_time'],
        'end_time' => $row['end_time'],
        'status' => $row['status'],
        'notes' => $row['notes'],
        'created_at' => date('M d, Y', strtotime($row['created_at']))
    ];
}

echo json_encode($appointments);
