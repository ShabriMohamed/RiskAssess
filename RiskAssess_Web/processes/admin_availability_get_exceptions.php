<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$counsellor_id = isset($_GET['counsellor_id']) ? intval($_GET['counsellor_id']) : 0;

if (!$counsellor_id) {
    echo json_encode([]);
    exit;
}

$exceptions = [];

$stmt = $conn->prepare("
    SELECT id, counsellor_id, exception_date, is_available, start_time, end_time, reason 
    FROM availability_exceptions 
    WHERE counsellor_id = ?
    ORDER BY exception_date DESC
");
$stmt->bind_param("i", $counsellor_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $exceptions[] = $row;
}

$stmt->close();

echo json_encode($exceptions);
