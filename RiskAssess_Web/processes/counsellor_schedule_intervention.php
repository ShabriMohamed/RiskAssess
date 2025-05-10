<?php
// processes/counsellor_schedule_intervention.php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_POST['client_id']) || !is_numeric($_POST['client_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid client ID']);
    exit;
}

$client_id = intval($_POST['client_id']);
$counsellor_user_id = $_SESSION['user_id'];
$intervention_date = $_POST['intervention_date'] ?? '';
$intervention_time = $_POST['intervention_time'] ?? '';
$intervention_duration = intval($_POST['intervention_duration'] ?? 60);
$intervention_type = $_POST['intervention_type'] ?? '';
$intervention_notes = $_POST['intervention_notes'] ?? '';
$notify_client = isset($_POST['notify_client']) && $_POST['notify_client'] === 'on';

// Validate inputs
if (empty($intervention_date) || empty($intervention_time) || empty($intervention_type)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Get counsellor ID
$stmt = $conn->prepare("SELECT id FROM counsellors WHERE user_id = ?");
$stmt->bind_param("i", $counsellor_user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Counsellor profile not found']);
    exit;
}
$counsellor_id = $result->fetch_assoc()['id'];

try {
    // Calculate end time
    $start_time = new DateTime($intervention_time);
    $end_time = clone $start_time;
    $end_time->add(new DateInterval('PT' . $intervention_duration . 'M'));
    
    $start_time_str = $start_time->format('H:i:s');
    $end_time_str = $end_time->format('H:i:s');
    
    // Create appointment
    $stmt = $conn->prepare("
        INSERT INTO appointments (counsellor_id, client_id, appointment_date, start_time, end_time, status, notes)
        VALUES (?, ?, ?, ?, ?, 'scheduled', ?)
    ");
    $stmt->bind_param("iissss", $counsellor_id, $client_id, $intervention_date, $start_time_str, $end_time_str, $intervention_notes);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("Failed to create appointment");
    }
    
    $appointment_id = $conn->insert_id;
    
    // Log the action
    $details = json_encode([
        'appointment_id' => $appointment_id,
        'client_id' => $client_id,
        'intervention_type' => $intervention_type,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $stmt = $conn->prepare("
        INSERT INTO audit_log (user_id, action, table_name, record_id, details)
        VALUES (?, 'Scheduled intervention', 'appointments', ?, ?)
    ");
    $stmt->bind_param("iis", $counsellor_user_id, $appointment_id, $details);
    $stmt->execute();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Intervention scheduled successfully',
        'appointment_id' => $appointment_id
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error scheduling intervention: ' . $e->getMessage()]);
}
?>
