<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$counsellor_user_id = $_SESSION['user_id'];
$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;

if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
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
    
    // Get appointment details
    $stmt = $conn->prepare("
        SELECT a.*, u.name as client_name, u.email as client_email, u.telephone as client_phone, up.profile_photo as client_photo
        FROM appointments a
        JOIN users u ON a.client_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE a.id = ? AND a.counsellor_id = ?
    ");
    
    $stmt->bind_param("ii", $appointment_id, $counsellor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit;
    }
    
    $appointment = $result->fetch_assoc();
    
    echo json_encode(['success' => true, 'appointment' => $appointment]);
    
} catch (Exception $e) {
    error_log('Error in counsellor_get_appointment.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error retrieving appointment details']);
}
