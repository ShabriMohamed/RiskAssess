<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get user profile data
    $stmt = $conn->prepare("
        SELECT u.name, u.email, u.address, u.telephone, u.role, u.status, u.created_at,
               up.date_of_birth, up.gender, up.occupation, up.emergency_contact_name, 
               up.emergency_contact_phone, up.preferred_contact_method, up.bio
        FROM users u
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $profile_data = $stmt->get_result()->fetch_assoc();
    
    // Format data for export
    $export_data = [
        'profile' => [
            'name' => $profile_data['name'],
            'email' => $profile_data['email'],
            'telephone' => $profile_data['telephone'],
            'address' => $profile_data['address'],
            'date_of_birth' => $profile_data['date_of_birth'],
            'gender' => $profile_data['gender'],
            'occupation' => $profile_data['occupation'],
            'bio' => $profile_data['bio'],
            'emergency_contact' => [
                'name' => $profile_data['emergency_contact_name'],
                'phone' => $profile_data['emergency_contact_phone']
            ],
            'preferred_contact_method' => $profile_data['preferred_contact_method'],
            'account_created' => $profile_data['created_at']
        ],
        'export_date' => date('Y-m-d H:i:s')
    ];
    
    // Log the action
    $action = "Exported profile data";
    $details = json_encode(['timestamp' => date('Y-m-d H:i:s')]);
    
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, details) VALUES (?, ?, 'users', ?, ?)");
    $stmt->bind_param("isis", $user_id, $action, $user_id, $details);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'data' => $export_data
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error exporting profile: ' . $e->getMessage()]);
}
