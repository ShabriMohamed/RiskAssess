<?php
require '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = $_POST['role'] ?? '';
$status = $_POST['status'] ?? 'active';
$address = trim($_POST['address'] ?? '');
$telephone = trim($_POST['telephone'] ?? '');

if ($id < 1 || $name === '' || $email === '' || $role === '' || $status === '') {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}
if (!in_array($role, ['admin', 'staff', 'customer'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid role.']);
    exit;
}
if (!in_array($status, ['active', 'disabled'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    exit;
}

// Check for duplicate email (exclude self)
$stmt = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=?");
$stmt->bind_param("si", $email, $id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already exists.']);
    exit;
}
$stmt->close();

$stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=?, status=?, address=?, telephone=? WHERE id=?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("ssssssi", $name, $email, $role, $status, $address, $telephone, $id);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'User updated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
}
$stmt->close();
?>
