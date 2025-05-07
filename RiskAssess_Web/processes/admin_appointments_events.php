<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get filter parameters
$counsellor_id = isset($_GET['counsellor_id']) ? intval($_GET['counsellor_id']) : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d');
$end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d', strtotime('+30 days'));

$events = [];

// Build query conditions
$conditions = [];
$params = [];
$types = '';

if ($counsellor_id) {
    $conditions[] = "a.counsellor_id = ?";
    $params[] = $counsellor_id;
    $types .= 'i';
}

if ($status) {
    $conditions[] = "a.status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($start_date) {
    $conditions[] = "a.appointment_date >= ?";
    $params[] = $start_date;
    $types .= 's';
} else {
    $conditions[] = "a.appointment_date >= ?";
    $params[] = $start;
    $types .= 's';
}

if ($end_date) {
    $conditions[] = "a.appointment_date <= ?";
    $params[] = $end_date;
    $types .= 's';
} else {
    $conditions[] = "a.appointment_date <= ?";
    $params[] = $end;
    $types .= 's';
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Get appointments
$query = "
    SELECT a.id, a.counsellor_id, a.client_id, a.appointment_date, a.start_time, a.end_time, a.status, a.notes,
           c.user_id as counsellor_user_id, cu.name as counsellor_name,
           cl.name as client_name
    FROM appointments a
    JOIN counsellors c ON a.counsellor_id = c.id
    JOIN users cu ON c.user_id = cu.id
    JOIN users cl ON a.client_id = cl.id
    $whereClause
    ORDER BY a.appointment_date, a.start_time
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $eventDate = $row['appointment_date'];
    $eventStart = $eventDate . 'T' . $row['start_time'];
    $eventEnd = $eventDate . 'T' . $row['end_time'];
    
    $events[] = [
        'id' => $row['id'],
        'title' => $row['client_name'] . ' with ' . $row['counsellor_name'],
        'start' => $eventStart,
        'end' => $eventEnd,
        'type' => 'appointment',
        'counsellor_id' => $row['counsellor_id'],
        'client_id' => $row['client_id'],
        'client_name' => $row['client_name'],
        'counsellor_name' => $row['counsellor_name'],
        'status' => $row['status'],
        'notes' => $row['notes']
    ];
}

$stmt->close();

echo json_encode($events);
