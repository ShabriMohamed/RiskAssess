<?php
// processes/counsellor_update_assessment.php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get the JSON data from the request
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

if (!isset($data['assessment_id']) || !is_numeric($data['assessment_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid assessment ID']);
    exit;
}

$assessment_id = intval($data['assessment_id']);
$counsellor_user_id = $_SESSION['user_id'];

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
    // Verify assessment belongs to this counsellor
    $stmt = $conn->prepare("
        SELECT id FROM risk_assessments 
        WHERE id = ? AND counsellor_id = ?
    ");
    $stmt->bind_param("ii", $assessment_id, $counsellor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Assessment not found or not authorized to update']);
        exit;
    }
    
    // Update assessment notes
    $notes = $data['notes'] ?? '';
    
    $stmt = $conn->prepare("
        UPDATE risk_assessments 
        SET notes = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("si", $notes, $assessment_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0 || $stmt->errno === 0) {
        // Log the action
        $details = json_encode([
            'assessment_id' => $assessment_id,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        $stmt = $conn->prepare("
            INSERT INTO audit_log (user_id, action, table_name, record_id, details)
            VALUES (?, 'Updated assessment notes', 'risk_assessments', ?, ?)
        ");
        $stmt->bind_param("iis", $counsellor_user_id, $assessment_id, $details);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Assessment notes updated successfully']);
    } else {
        throw new Exception("Failed to update assessment notes");
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error updating assessment: ' . $e->getMessage()]);
}
?>
