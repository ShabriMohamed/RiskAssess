<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validate inputs
if (empty($current_password)) {
    echo json_encode(['success' => false, 'message' => 'Current password is required', 'field' => 'current_password']);
    exit;
}

if (empty($new_password)) {
    echo json_encode(['success' => false, 'message' => 'New password is required', 'field' => 'new_password']);
    exit;
}

if (strlen($new_password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long', 'field' => 'new_password']);
    exit;
}

if ($new_password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match', 'field' => 'confirm_password']);
    exit;
}

try {
    // Get current password hash
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect', 'field' => 'current_password']);
        exit;
    }
    
    // Hash new password
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Update password
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $password_hash, $user_id);
    $stmt->execute();
    
    // Update last password change in security table
    $now = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("SELECT user_id FROM user_security WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE user_security SET last_password_change = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("si", $now, $user_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO user_security (user_id, last_password_change, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $stmt->bind_param("is", $user_id, $now);
    }
    $stmt->execute();
    
    // Log the action
    $action = "Changed password";
    $details = json_encode(['timestamp' => $now]);
    
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, details) VALUES (?, ?, 'users', ?, ?)");
    $stmt->bind_param("isis", $user_id, $action, $user_id, $details);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error changing password: ' . $e->getMessage()]);
}
