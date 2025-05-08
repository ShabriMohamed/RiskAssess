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

if (!$counselor_user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid counselor ID']);
    exit;
}

try {
    // Mark messages as read
    $stmt = $conn->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ");
    
    $stmt->bind_param("ii", $counselor_user_id, $user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log('Error in mark_messages_read.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error marking messages as read']);
}
