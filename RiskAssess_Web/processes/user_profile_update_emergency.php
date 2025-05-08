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
$emergency_contact_name = filter_input(INPUT_POST, 'emergency_contact_name', FILTER_SANITIZE_STRING);
$emergency_contact_phone = filter_input(INPUT_POST, 'emergency_contact_phone', FILTER_SANITIZE_STRING);

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Check if user_profiles record exists
    $stmt = $conn->prepare("SELECT user_id FROM user_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing profile
        $stmt = $conn->prepare("UPDATE user_profiles SET emergency_contact_name = ?, emergency_contact_phone = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("ssi", $emergency_contact_name, $emergency_contact_phone, $user_id);
    } else {
        // Create new profile
        $stmt = $conn->prepare("INSERT INTO user_profiles (user_id, emergency_contact_name, emergency_contact_phone, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("iss", $user_id, $emergency_contact_name, $emergency_contact_phone);
    }
    $stmt->execute();
    
    // Log the action
    $action = "Updated emergency contact information";
    $details = json_encode([
        'fields_updated' => ['emergency_contact_name', 'emergency_contact_phone'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, details) VALUES (?, ?, 'user_profiles', ?, ?)");
    $stmt->bind_param("isis", $user_id, $action, $user_id, $details);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Emergency contact information updated successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error updating emergency contact information: ' . $e->getMessage()]);
}
