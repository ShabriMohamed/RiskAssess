<?php
require '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
if ($id < 1 || !in_array($action, ['enable', 'disable'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}
$status = ($action == 'enable') ? 'active' : 'disabled';

$stmt = $conn->prepare("UPDATE users SET status=? WHERE id=?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("si", $status, $id);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'User status updated.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
}
$stmt->close();
?>
