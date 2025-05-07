<?php
require '../config.php';
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']); exit;
}
$user_id = intval($_POST['user_id'] ?? 0);
$license_number = trim($_POST['license_number'] ?? '');
$specialties = trim($_POST['specialties'] ?? '');
$bio = trim($_POST['bio'] ?? '');

// Validate user
$stmt = $conn->prepare("SELECT role FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();
if ($role !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Only staff can be assigned as counsellors.']); exit;
}

// Validate and upload photo
if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Profile photo is required.']); exit;
}
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['profile_photo']['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid image type. Only JPEG, PNG, GIF allowed.']); exit;
}
if ($_FILES['profile_photo']['size'] > 2 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Image must be less than 2MB.']); exit;
}
if (!getimagesize($_FILES['profile_photo']['tmp_name'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid image file.']); exit;
}
$ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
$photo_name = 'counsellor_'.$user_id.'_'.time().'.'.$ext;
$upload_dir = dirname(__DIR__).'/uploads/counsellors/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_dir.$photo_name);

// Check not already assigned
$stmt = $conn->prepare("SELECT id FROM counsellors WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'This user is already a counsellor.']); exit;
}
$stmt->close();

$stmt = $conn->prepare("INSERT INTO counsellors (user_id, license_number, specialties, bio, profile_photo) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("issss", $user_id, $license_number, $specialties, $bio, $photo_name);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Counsellor assigned successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
}
$stmt->close();
