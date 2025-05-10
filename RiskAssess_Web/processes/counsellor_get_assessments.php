<?php
// processes/counsellor_get_assessments.php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

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
    // Get all assessments for this counsellor
    $stmt = $conn->prepare("
        SELECT ra.*, u.name as client_name, u.email as client_email, up.profile_photo
        FROM risk_assessments ra
        JOIN users u ON ra.client_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE ra.counsellor_id = ?
        ORDER BY ra.assessment_date DESC
    ");
    $stmt->bind_param("i", $counsellor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $assessments = [];
    while ($row = $result->fetch_assoc()) {
        $assessments[] = $row;
    }
    
    echo json_encode(['success' => true, 'assessments' => $assessments]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error retrieving assessments: ' . $e->getMessage()]);
}
?>
