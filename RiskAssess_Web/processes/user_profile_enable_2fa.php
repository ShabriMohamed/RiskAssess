<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$verification_code = $_POST['verification_code'] ?? '';

// Validate verification code
if (empty($verification_code) || strlen($verification_code) !== 6 || !ctype_digit($verification_code)) {
    echo json_encode(['success' => false, 'message' => 'Invalid verification code']);
    exit;
}

// Check if temporary secret exists in session
if (!isset($_SESSION['2fa_temp_secret'])) {
    echo json_encode(['success' => false, 'message' => '2FA setup session expired. Please try again.']);
    exit;
}

$secret = $_SESSION['2fa_temp_secret'];

// Require the 2FA library
require_once '../vendor/autoload.php';

try {
    // Create 2FA object
    $tfa = new RobThree\Auth\TwoFactorAuth('RiskAssess');
    
    // Verify the code
    $isValid = $tfa->verifyCode($secret, $verification_code);
    
    if (!$isValid) {
        echo json_encode(['success' => false, 'message' => 'Invalid verification code. Please try again.']);
        exit;
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Check if user_security record exists
    $stmt = $conn->prepare("SELECT user_id FROM user_security WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing record
        $stmt = $conn->prepare("UPDATE user_security SET two_factor_enabled = 1, two_factor_secret = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("si", $secret, $user_id);
    } else {
        // Create new record
        $stmt = $conn->prepare("INSERT INTO user_security (user_id, two_factor_enabled, two_factor_secret, created_at, updated_at) VALUES (?, 1, ?, NOW(), NOW())");
        $stmt->bind_param("is", $user_id, $secret);
    }
    $stmt->execute();
    
    // Log the action
    $action = "Enabled two-factor authentication";
    $details = json_encode(['timestamp' => date('Y-m-d H:i:s')]);
    
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, details) VALUES (?, ?, 'user_security', ?, ?)");
    $stmt->bind_param("isis", $user_id, $action, $user_id, $details);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Remove temporary secret from session
    unset($_SESSION['2fa_temp_secret']);
    
    echo json_encode(['success' => true, 'message' => 'Two-factor authentication enabled successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => 'Error enabling two-factor authentication: ' . $e->getMessage()]);
}
