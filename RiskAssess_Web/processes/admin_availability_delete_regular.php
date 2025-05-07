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
    echo json_encode(['success' => false, 'message' => 'Invalid schedule ID']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM counsellor_availability WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Schedule deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error deleting schedule: ' . $stmt->error]);
}
$stmt->close();
