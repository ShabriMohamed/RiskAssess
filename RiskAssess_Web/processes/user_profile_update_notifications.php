<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get notification preferences
$email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
$sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
$app_notifications = isset($_POST['app_notifications']) ? 1 : 0;

// Get notification types
$appointment_reminders = isset($_POST['appointment_reminders']) ? 1 : 0;
$appointment_changes = isset($_POST['appointment_changes']) ? 1 : 0;
$counselor_messages = isset($_POST['counselor_messages']) ? 1 : 0;
$system_updates = isset($_POST['system_updates']) ? 1 : 0;

// Get reminder time
$reminder_time = filter_input(INPUT_POST, 'reminder_time', FILTER_SANITIZE_NUMBER_INT);

// Create notification preferences JSON
$notification_preferences = json_encode([
    'email' => (bool)$email_notifications,
    'sms' => (bool)$sms_notifications,
    'app' => (bool)$app_notifications,
    'types' => [
        'appointment_reminders' => (bool)$appointment_reminders,
        'appointment_changes' => (bool)$appointment_changes,
        'counselor_messages' => (bool)$counselor_messages,
        'system_updates' => (bool)$system_updates
    ],
    'reminder_time' => $reminder_time
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
        $stmt = $conn->prepare("UPDATE user_profiles SET notification_preferences = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("si", $notification_preferences, $user_id);
    } else {
        // Create new profile
        $stmt = $conn->prepare("INSERT INTO user_profiles (user_id, notification_preferences, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $stmt->bind_param("is", $user_id, $notification_preferences);
    }
    $stmt->execute();
    
    // Log the action
    $action = "Updated notification preferences";
    $details = json_encode(['timestamp' => date('Y-m-d H:i:s')]);
    
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, details) VALUES (?, ?, 'user_profiles', ?, ?)");
    $stmt->bind_param("isis", $user_id, $action, $user_id, $details);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Notification preferences updated successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error updating notification preferences: ' . $e->getMessage()]);
}
