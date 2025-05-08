<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get privacy settings
$share_profile_data = isset($_POST['share_profile_data']) ? 1 : 0;
$share_contact_info = isset($_POST['share_contact_info']) ? 1 : 0;
$share_anonymous_data = isset($_POST['share_anonymous_data']) ? 1 : 0;
$data_retention_period = filter_input(INPUT_POST, 'data_retention_period', FILTER_SANITIZE_NUMBER_INT);
$auto_delete_messages = isset($_POST['auto_delete_messages']) ? 1 : 0;

// Create privacy settings JSON
$privacy_settings = json_encode([
    'share_profile_data' => (bool)$share_profile_data,
    'share_contact_info' => (bool)$share_contact_info,
    'share_anonymous_data' => (bool)$share_anonymous_data,
    'data_retention_period' => $data_retention_period,
    'auto_delete_messages' => (bool)$auto_delete_messages,
    'updated_at' => date('Y-m-d H:i:s')
]);

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
        $stmt = $conn->prepare("UPDATE user_profiles SET privacy_settings = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("si", $privacy_settings, $user_id);
    } else {
        // Create new profile
        $stmt = $conn->prepare("INSERT INTO user_profiles (user_id, privacy_settings, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $stmt->bind_param("is", $user_id, $privacy_settings);
    }
    $stmt->execute();
    
    // Log the action
    $action = "Updated privacy settings";
    $details = json_encode(['timestamp' => date('Y-m-d H:i:s')]);
    
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, details) VALUES (?, ?, 'user_profiles', ?, ?)");
    $stmt->bind_param("isis", $user_id, $action, $user_id, $details);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Privacy settings updated successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error updating privacy settings: ' . $e->getMessage()]);
}
