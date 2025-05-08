<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

// Add this to user_profile_upload_photo.php
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if file was uploaded
if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

// Validate file type
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$file_type = $_FILES['profile_photo']['type'];

if (!in_array($file_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed']);
    exit;
}

// Validate file size (max 5MB)
$max_size = 5 * 1024 * 1024; // 5MB
if ($_FILES['profile_photo']['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
    exit;
}

// Create uploads directory if it doesn't exist
$upload_dir = '../uploads/profiles/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
$filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
$target_file = $upload_dir . $filename;

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Move uploaded file
    if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    // Get current profile photo
    $stmt = $conn->prepare("SELECT profile_photo FROM user_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $old_photo = null;
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $old_photo = $row['profile_photo'];
        
        // Update profile photo
        $stmt = $conn->prepare("UPDATE user_profiles SET profile_photo = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("si", $filename, $user_id);
    } else {
        // Create new profile record
        $stmt = $conn->prepare("INSERT INTO user_profiles (user_id, profile_photo, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $stmt->bind_param("is", $user_id, $filename);
    }
    $stmt->execute();
    
    // Delete old photo if exists
    if ($old_photo && file_exists($upload_dir . $old_photo)) {
        unlink($upload_dir . $old_photo);
    }
    
    // Log the action
    $action = "Updated profile photo";
    $details = json_encode(['filename' => $filename, 'timestamp' => date('Y-m-d H:i:s')]);
    
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, details) VALUES (?, ?, 'user_profiles', ?, ?)");
    $stmt->bind_param("isis", $user_id, $action, $user_id, $details);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Profile photo updated successfully',
        'photo_url' => 'uploads/profiles/' . $filename
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Delete uploaded file if exists
    if (file_exists($target_file)) {
        unlink($target_file);
    }
    
    echo json_encode(['success' => false, 'message' => 'Error uploading profile photo: ' . $e->getMessage()]);
}
