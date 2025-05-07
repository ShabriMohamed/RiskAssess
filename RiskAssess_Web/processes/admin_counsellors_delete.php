<?php
require '../config.php';
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']); exit;
}
$id = intval($_POST['id'] ?? 0);
if ($id < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid counsellor ID.']); exit;
}
// Delete photo
$stmt = $conn->prepare("SELECT profile_photo FROM counsellors WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
if ($row && $row['profile_photo']) {
    $upload_dir = dirname(__DIR__).'/uploads/counsellors/';
    $file = $upload_dir.$row['profile_photo'];
    if (file_exists($file)) unlink($file);
}
$stmt = $conn->prepare("DELETE FROM counsellors WHERE id=?");
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Counsellor removed successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
}
$stmt->close();
