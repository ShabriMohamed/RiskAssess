<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Require the 2FA library (you'll need to install this via composer)
// composer require robthree/twofactorauth
require_once '../vendor/autoload.php';

try {
    // Create 2FA object
    $tfa = new RobThree\Auth\TwoFactorAuth('RiskAssess');
    
    // Generate a new secret key
    $secret = $tfa->createSecret();
    
    // Store the secret in the session for later verification
    $_SESSION['2fa_temp_secret'] = $secret;
    
    // Get user email for the QR code label
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    // Generate QR code URL
    $qrCodeUrl = $tfa->getQRCodeImageAsDataUri($user['email'], $secret);
    
    echo json_encode([
        'success' => true,
        'secret' => $secret,
        'qr_url' => $qrCodeUrl
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error generating 2FA setup: ' . $e->getMessage()]);
}
