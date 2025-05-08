<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$limit = 10;

try {
    // Get activity log
    $stmt = $conn->prepare("
        SELECT action, created_at, details
        FROM audit_log
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ?, ?
    ");
    $stmt->bind_param("iii", $user_id, $offset, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $activities = [];
    while ($row = $result->fetch_assoc()) {
        // Format date for display
        $created_at = new DateTime($row['created_at']);
        $row['created_at'] = $created_at->format('M j, Y g:i A');
        
        $activities[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'activities' => $activities
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error retrieving activity log: ' . $e->getMessage()]);
}
