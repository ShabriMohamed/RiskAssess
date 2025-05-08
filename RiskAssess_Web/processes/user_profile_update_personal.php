<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Validate and sanitize input
$name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$telephone = filter_input(INPUT_POST, 'telephone', FILTER_SANITIZE_STRING);
$address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
$date_of_birth = filter_input(INPUT_POST, 'date_of_birth', FILTER_SANITIZE_STRING);
$gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
$occupation = filter_input(INPUT_POST, 'occupation', FILTER_SANITIZE_STRING);
$bio = filter_input(INPUT_POST, 'bio', FILTER_SANITIZE_STRING);

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Check if email already exists for another user
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already in use by another account']);
        exit;
    }
    
    // Update user table
    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, telephone = ?, address = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $name, $email, $telephone, $address, $user_id);
    $stmt->execute();
    
    // Check if user_profiles record exists
    $stmt = $conn->prepare("SELECT user_id FROM user_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing profile
        $stmt = $conn->prepare("UPDATE user_profiles SET date_of_birth = ?, gender = ?, occupation = ?, bio = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("ssssi", $date_of_birth, $gender, $occupation, $bio, $user_id);
    } else {
        // Create new profile
        $stmt = $conn->prepare("INSERT INTO user_profiles (user_id, date_of_birth, gender, occupation, bio, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("issss", $user_id, $date_of_birth, $gender, $occupation, $bio);
    }
    $stmt->execute();
    
    // Log the action
    $action = "Updated personal information";
    $details = json_encode([
        'fields_updated' => ['name', 'email', 'telephone', 'address', 'date_of_birth', 'gender', 'occupation', 'bio'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, details) VALUES (?, ?, 'users', ?, ?)");
    $stmt->bind_param("isis", $user_id, $action, $user_id, $details);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Update session data
    $_SESSION['name'] = $name;
    $_SESSION['email'] = $email;
    
    echo json_encode(['success' => true, 'message' => 'Personal information updated successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error updating personal information: ' . $e->getMessage()]);
}
