<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$counselor_user_id = isset($_POST['counselor_user_id']) ? (int)$_POST['counselor_user_id'] : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if (!$counselor_user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid counselor ID']);
    exit;
}

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit;
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Insert message
    $stmt = $conn->prepare("
        INSERT INTO messages (sender_id, receiver_id, message, sent_at, is_read)
        VALUES (?, ?, ?, NOW(), 0)
    ");
    
    $stmt->bind_param("iis", $user_id, $counselor_user_id, $message);
    $stmt->execute();
    $message_id = $stmt->insert_id;
    
    // Log the action
    $action = "Sent message to counselor";
    $details = json_encode([
        'counselor_id' => $counselor_user_id,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $stmt = $conn->prepare("
        INSERT INTO audit_log (user_id, action, table_name, record_id, details)
        VALUES (?, ?, 'messages', ?, ?)
    ");
    $stmt->bind_param("isis", $user_id, $action, $message_id, $details);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message_id' => $message_id]);
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    error_log('Error in send_message.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error sending message']);
}
