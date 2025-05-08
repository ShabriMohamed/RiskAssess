<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$password = $_POST['password'] ?? '';

// Validate password
if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password is required', 'field' => 'password']);
    exit;
}

try {
    // Verify password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Incorrect password', 'field' => 'password']);
        exit;
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Disable 2FA
    $stmt = $conn->prepare("UPDATE user_security SET two_factor_enabled = 0, two_factor_secret = NULL, updated_at = NOW() WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Log the action
    $action = "Disabled two-factor authentication";
    $details = json_encode(['timestamp' => date('Y-m-d H:i:s')]);
    
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, details) VALUES (?, ?, 'user_security', ?, ?)");
    $stmt->bind_param("isis", $user_id, $action, $user_id, $details);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Two-factor authentication disabled successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error disabling two-factor authentication: ' . $e->getMessage()]);
}
