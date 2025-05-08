<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$current_session_id = session_id();

try {
    // In a real application, you would have a sessions table
    // For this example, we'll create mock data
    
    $sessions = [
        [
            'id' => $current_session_id,
            'device_type' => 'desktop',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'location' => 'Unknown',
            'last_activity' => date('M j, Y g:i A'),
            'is_current' => true
        ]
    ];
    
    // Add some mock sessions
    $devices = ['desktop', 'mobile', 'tablet'];
    $browsers = ['Chrome', 'Firefox', 'Safari', 'Edge'];
    $os_list = ['Windows', 'MacOS', 'iOS', 'Android'];
    
    for ($i = 0; $i < 3; $i++) {
        $sessions[] = [
            'id' => md5(uniqid()),
            'device_type' => $devices[array_rand($devices)],
            'browser' => $browsers[array_rand($browsers)],
            'os' => $os_list[array_rand($os_list)],
            'ip_address' => '192.168.' . rand(1, 255) . '.' . rand(1, 255),
            'location' => 'Unknown',
            'last_activity' => date('M j, Y g:i A', time() - rand(1, 10) * 3600),
            'is_current' => false
        ];
    }
    
    echo json_encode([
        'success' => true,
        'sessions' => $sessions
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error retrieving sessions: ' . $e->getMessage()]);
}
