<?php
require '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id < 1) {
    echo json_encode(['error' => 'Invalid user ID.']);
    exit;
}

$stmt = $conn->prepare("SELECT id, name, email, role, address, telephone, status FROM users WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if ($user) {
    echo json_encode($user);
} else {
    echo json_encode(['error' => 'User not found.']);
}
?>
