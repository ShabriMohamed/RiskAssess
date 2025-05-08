<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$session_id = $_POST['session_id'] ?? '';

if (empty($session_id)) {
    echo json_encode(['success' => false, 'message' => 'Session ID is required']);
    exit;
}

// Prevent logging out current session
if ($session_id === session_id()) {
    echo json_encode(['success' => false, 'message' => 'Cannot logout current session']);
    exit;
}

try {
    
    
    // Log the action
    $action = "Logged out session";
    $details = json_encode([
        'session_id' => $session_id,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, details) VALUES (?, ?, 'sessions', ?, ?)");
    $stmt->bind_param("isis", $user_id, $action, $user_id, $details);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Session logged out successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error logging out session: ' . $e->getMessage()]);
}
