<?php
require '../config.php';
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']); exit;
}
$id = intval($_GET['id'] ?? 0);
if ($id < 1) {
    echo json_encode(['error' => 'Invalid counsellor ID.']); exit;
}
$stmt = $conn->prepare("SELECT id, license_number, specialties, bio, profile_photo FROM counsellors WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$c = $res->fetch_assoc();
$stmt->close();
if ($c) echo json_encode($c);
else echo json_encode(['error' => 'Counsellor not found.']);
