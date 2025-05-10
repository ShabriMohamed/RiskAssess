<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$counsellor_user_id = $_SESSION['user_id'];

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
    
    // Get all clients with appointment statistics
    $stmt = $conn->prepare("
        SELECT 
            u.id, u.name, u.email, u.telephone, u.address, up.profile_photo,
            MAX(a.appointment_date) as last_appointment_date,
            COUNT(a.id) as appointment_count,
            SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_sessions,
            SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_sessions,
            SUM(CASE WHEN a.status = 'no-show' THEN 1 ELSE 0 END) as no_show_sessions,
            (SELECT MIN(appointment_date) FROM appointments 
             WHERE client_id = u.id AND counsellor_id = ? AND appointment_date >= CURDATE() AND status = 'scheduled') as next_appointment_date,
            (SELECT COUNT(*) FROM appointments 
             WHERE client_id = u.id AND counsellor_id = ? AND appointment_date >= CURDATE() AND status = 'scheduled') as upcoming_sessions
        FROM appointments a
        JOIN users u ON a.client_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE a.counsellor_id = ? AND u.status = 'active'
        GROUP BY u.id
        ORDER BY last_appointment_date DESC
    ");
    
    $stmt->bind_param("iii", $counsellor_id, $counsellor_id, $counsellor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $clients = [];
    while ($row = $result->fetch_assoc()) {
        $clients[] = $row;
    }
    
    echo json_encode(['success' => true, 'clients' => $clients]);
    
} catch (Exception $e) {
    error_log('Error in counsellor_get_clients.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error retrieving clients']);
}
