<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$counselor_user_id = isset($_GET['counselor_user_id']) ? (int)$_GET['counselor_user_id'] : 0;

if (!$counselor_user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid counselor ID']);
    exit;
}

try {
    // Get counselor ID from user_id
    $stmt = $conn->prepare("SELECT id FROM counsellors WHERE user_id = ?");
    $stmt->bind_param("i", $counselor_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Counselor not found']);
        exit;
    }
    
    $counselor_id = $result->fetch_assoc()['id'];
    
    // Get messages between user and counselor
    $stmt = $conn->prepare("
        SELECT m.*, 
               u.name as sender_name,
               CASE 
                   WHEN u.role = 'staff' THEN CONCAT('uploads/counsellors/', c.profile_photo)
                   ELSE CONCAT('uploads/profiles/', up.profile_photo)
               END as sender_avatar
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        LEFT JOIN counsellors c ON u.id = c.user_id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) 
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.sent_at ASC
    ");
    
    $stmt->bind_param("iiii", $user_id, $counselor_user_id, $counselor_user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        // Format date and time
        $sent_at = new DateTime($row['sent_at']);
        $row['time'] = $sent_at->format('g:i A');
        $row['date'] = $sent_at->format('Y-m-d');
        
        // Handle null avatar
        if (empty($row['sender_avatar']) || strpos($row['sender_avatar'], 'null') !== false) {
            $row['sender_avatar'] = 'assets/img/default-profile.png';
        }
        
        $messages[] = $row;
    }
    
    // Mark messages as read
    $stmt = $conn->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ");
    $stmt->bind_param("ii", $counselor_user_id, $user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'messages' => $messages, 'counselor_id' => $counselor_id]);
    
} catch (Exception $e) {
    error_log('Error in get_messages.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error retrieving messages']);
}
