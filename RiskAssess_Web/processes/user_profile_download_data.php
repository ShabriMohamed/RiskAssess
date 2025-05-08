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
    // Get user data
    $stmt = $conn->prepare("
        SELECT u.name, u.email, u.address, u.telephone, u.role, u.status, u.created_at,
               up.date_of_birth, up.gender, up.occupation, up.emergency_contact_name, 
               up.emergency_contact_phone, up.preferred_contact_method, up.bio, 
               up.notification_preferences, up.last_login
        FROM users u
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    
    // Get appointments
    $stmt = $conn->prepare("
        SELECT a.appointment_date, a.start_time, a.end_time, a.status, a.notes, a.created_at,
               c.id as counsellor_id, u.name as counsellor_name
        FROM appointments a
        JOIN counsellors c ON a.counsellor_id = c.id
        JOIN users u ON c.user_id = u.id
        WHERE a.client_id = ?
        ORDER BY a.appointment_date DESC, a.start_time DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    
    // Get activity log
    $stmt = $conn->prepare("
        SELECT action, table_name, details, created_at
        FROM audit_log
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    
    // Compile all data
    $data = [
        'user_information' => $user_data,
        'appointments' => $appointments,
        'activity_log' => $activities,
        'export_date' => date('Y-m-d H:i:s'),
        'export_requested_by' => $user_data['name']
    ];
    
    // Log the action
    $action = "Downloaded personal data";
    $details = json_encode(['timestamp' => date('Y-m-d H:i:s')]);
    
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, details) VALUES (?, ?, 'users', ?, ?)");
    $stmt->bind_param("isis", $user_id, $action, $user_id, $details);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error downloading data: ' . $e->getMessage()]);
}
