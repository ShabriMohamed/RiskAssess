<?php
require '../config.php';
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']); exit;
}
$id = intval($_POST['id'] ?? 0);
$license_number = trim($_POST['license_number'] ?? '');
$specialties = trim($_POST['specialties'] ?? '');
$bio = trim($_POST['bio'] ?? '');

// Get current photo
$stmt = $conn->prepare("SELECT profile_photo FROM counsellors WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$current_photo = $row ? $row['profile_photo'] : null;
$stmt->close();

$photo_name = $current_photo;
if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
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
    $photo_name = 'counsellor_'.$id.'_'.time().'.'.$ext;
    $upload_dir = dirname(__DIR__).'/uploads/counsellors/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_dir.$photo_name);
    // Optionally, delete old photo
    if ($current_photo && file_exists($upload_dir.$current_photo)) unlink($upload_dir.$current_photo);
}

$stmt = $conn->prepare("UPDATE counsellors SET license_number=?, specialties=?, bio=?, profile_photo=? WHERE id=?");
$stmt->bind_param("ssssi", $license_number, $specialties, $bio, $photo_name, $id);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Counsellor updated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
}
$stmt->close();
