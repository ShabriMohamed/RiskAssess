<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$counsellor_user_id = $_SESSION['user_id'];
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

if (!$client_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid client ID']);
    exit;
}

try {
    // Get counsellor ID
    $stmt = $conn->prepare("SELECT id FROM counsellors WHERE user_id = ?");
    $stmt->bind_param("i", $counsellor_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Counsellor profile not found']);
        exit;
    }
    
    $counsellor_id = $result->fetch_assoc()['id'];
    
    // Verify client has appointments with this counsellor
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM appointments
        WHERE counsellor_id = ? AND client_id = ?
    ");
    $stmt->bind_param("ii", $counsellor_id, $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    if ($count === 0) {
        echo json_encode(['success' => false, 'message' => 'Client not found or not associated with this counsellor']);
        exit;
    }
    
    // Get client details
    $stmt = $conn->prepare("
        SELECT u.*, up.*
        FROM users u
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE u.id = ? AND u.status = 'active'
    ");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Client not found']);
        exit;
    }
    
    $client = $result->fetch_assoc();
    
    // Log the action
    $action = "Viewed client profile";
    $details = json_encode([
        'client_id' => $client_id,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $stmt = $conn->prepare("
        INSERT INTO audit_log (user_id, action, table_name, record_id, details)
        VALUES (?, ?, 'users', ?, ?)
    ");
    $stmt->bind_param("isis", $counsellor_user_id, $action, $client_id, $details);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'client' => $client]);
    
} catch (Exception $e) {
    error_log('Error in counsellor_get_client.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error retrieving client profile']);
}
