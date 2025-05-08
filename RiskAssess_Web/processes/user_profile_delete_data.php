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
$reason = $_POST['reason'] ?? '';

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
    
    // Cancel all upcoming appointments
    $stmt = $conn->prepare("
        UPDATE appointments 
        SET status = 'cancelled', 
            notes = CONCAT(IFNULL(notes, ''), '\n\nCancelled due to account deletion')
        WHERE client_id = ? AND status = 'scheduled'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    
    
    // Mark account for deletion
    $stmt = $conn->prepare("UPDATE users SET status = 'deleted', email = CONCAT('deleted_', id, '@example.com'), name = 'Deleted User' WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Delete profile data
    $stmt = $conn->prepare("DELETE FROM user_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Delete security data
    $stmt = $conn->prepare("DELETE FROM user_security WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Log the action
    $action = "Requested data deletion";
    $details = json_encode([
        'reason' => $reason,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, details) VALUES (?, ?, 'users', ?, ?)");
    $stmt->bind_param("isis", $user_id, $action, $user_id, $details);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Your data has been marked for deletion. You will be logged out shortly.']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error processing data deletion: ' . $e->getMessage()]);
}
