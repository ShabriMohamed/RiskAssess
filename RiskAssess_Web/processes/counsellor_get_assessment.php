<?php
// processes/counsellor_get_assessment.php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['assessment_id']) || !is_numeric($_GET['assessment_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid assessment ID']);
    exit;
}

$assessment_id = intval($_GET['assessment_id']);
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
    // Get assessment details
    $stmt = $conn->prepare("
        SELECT ra.*, u.name as client_name, u.email as client_email, u.telephone as client_phone, 
               up.profile_photo, u.id as client_id
        FROM risk_assessments ra
        JOIN users u ON ra.client_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE ra.id = ? AND ra.counsellor_id = ?
    ");
    $stmt->bind_param("ii", $assessment_id, $counsellor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Assessment not found or not authorized to view']);
        exit;
    }
    
    $assessment = $result->fetch_assoc();
    
    // Get previous assessment for comparison
    $stmt = $conn->prepare("
        SELECT risk_level, risk_score, assessment_date
        FROM risk_assessments
        WHERE client_id = ? AND counsellor_id = ? AND id != ?
        ORDER BY assessment_date DESC
        LIMIT 1
    ");
    $stmt->bind_param("iii", $assessment['client_id'], $counsellor_id, $assessment_id);
    $stmt->execute();
    $prev_result = $stmt->get_result();
    
    if ($prev_result->num_rows > 0) {
        $prev_assessment = $prev_result->fetch_assoc();
        $assessment['previous_assessment'] = $prev_assessment;
        
        // Calculate risk trend
        $current_score = $assessment['risk_score'];
        $prev_score = $prev_assessment['risk_score'];
        $score_diff = $current_score - $prev_score;
        
        if (abs($score_diff) < 5) {
            $assessment['risk_trend'] = 'stable';
        } else if ($score_diff > 0) {
            $assessment['risk_trend'] = 'increasing';
        } else {
            $assessment['risk_trend'] = 'decreasing';
        }
    } else {
        $assessment['previous_assessment'] = null;
        $assessment['risk_trend'] = 'initial';
    }
    
    // Parse questionnaire data and key factors
    $assessment['questionnaire_data'] = json_decode($assessment['questionnaire_data'], true);
    $assessment['key_factors'] = json_decode($assessment['key_factors'], true);
    
    echo json_encode(['success' => true, 'assessment' => $assessment]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error retrieving assessment details: ' . $e->getMessage()]);
}
?>
