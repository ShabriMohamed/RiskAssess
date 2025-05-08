<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d');
$end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d', strtotime('+30 days'));

$events = [];

// Get all appointments for the user
$stmt = $conn->prepare("
    SELECT a.id, a.counsellor_id, a.appointment_date, a.start_time, a.end_time, a.status, a.notes,
           u.name as counsellor_name
    FROM appointments a
    JOIN counsellors c ON a.counsellor_id = c.id
    JOIN users u ON c.user_id = u.id
    WHERE a.client_id = ? AND a.appointment_date BETWEEN ? AND ?
    ORDER BY a.appointment_date, a.start_time
");
$stmt->bind_param("iss", $user_id, $start, $end);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $eventDate = $row['appointment_date'];
    $eventStart = $eventDate . 'T' . $row['start_time'];
    $eventEnd = $eventDate . 'T' . $row['end_time'];
    
    // Determine color based on status
    $color = '#4f8cff'; // Default blue for scheduled
    switch ($row['status']) {
        case 'completed': $color = '#28a745'; break;
        case 'cancelled': $color = '#dc3545'; break;
        case 'no-show': $color = '#6c757d'; break;
    }
    
    $events[] = [
        'id' => $row['id'],
        'title' => 'Session with ' . $row['counsellor_name'],
        'start' => $eventStart,
        'end' => $eventEnd,
        'backgroundColor' => $color,
        'borderColor' => $color,
        'textColor' => '#ffffff',
        'extendedProps' => [
            'counsellor_id' => $row['counsellor_id'],
            'counsellor_name' => $row['counsellor_name'],
            'status' => $row['status'],
            'notes' => $row['notes']
        ]
    ];
}

$stmt->close();

echo json_encode($events);
