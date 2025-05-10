<?php
// processes/counsellor_get_chart_data.php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$counsellor_user_id = $_SESSION['user_id'];
$period = isset($_GET['period']) ? $_GET['period'] : 'month';

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
    // Set date range based on period
    $end_date = date('Y-m-d');
    $start_date = '';
    
    switch ($period) {
        case 'month':
            $start_date = date('Y-m-d', strtotime('-30 days'));
            $interval = 'DAY';
            $format = '%Y-%m-%d';
            break;
        case 'quarter':
            $start_date = date('Y-m-d', strtotime('-90 days'));
            $interval = 'WEEK';
            $format = '%Y-%u'; // Year and week number
            break;
        case 'year':
            $start_date = date('Y-m-d', strtotime('-1 year'));
            $interval = 'MONTH';
            $format = '%Y-%m';
            break;
        default: // all time
            $start_date = '2000-01-01'; // A date far in the past
            $interval = 'MONTH';
            $format = '%Y-%m';
            break;
    }
    
    // Get risk distribution over time
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(assessment_date, ?) as time_period,
            SUM(CASE WHEN risk_level = 'High' THEN 1 ELSE 0 END) as high_risk,
            SUM(CASE WHEN risk_level = 'Moderate' THEN 1 ELSE 0 END) as moderate_risk,
            SUM(CASE WHEN risk_level = 'Low' THEN 1 ELSE 0 END) as low_risk,
            AVG(risk_score) as avg_risk_score
        FROM risk_assessments
        WHERE counsellor_id = ? AND assessment_date BETWEEN ? AND ?
        GROUP BY time_period
        ORDER BY time_period
    ");
    $stmt->bind_param("siss", $format, $counsellor_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $labels = [];
    $high_risk = [];
    $moderate_risk = [];
    $low_risk = [];
    $avg_scores = [];
    
    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['time_period'];
        $high_risk[] = intval($row['high_risk']);
        $moderate_risk[] = intval($row['moderate_risk']);
        $low_risk[] = intval($row['low_risk']);
        $avg_scores[] = floatval($row['avg_risk_score']);
    }
    
    // Get overall metrics for the period
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN risk_level = 'High' THEN 1 ELSE 0 END) as high_risk,
            SUM(CASE WHEN risk_level = 'Moderate' THEN 1 ELSE 0 END) as moderate_risk,
            SUM(CASE WHEN risk_level = 'Low' THEN 1 ELSE 0 END) as low_risk,
            AVG(risk_score) as avg_risk_score
        FROM risk_assessments
        WHERE counsellor_id = ? AND assessment_date BETWEEN ? AND ?
    ");
    $stmt->bind_param("iss", $counsellor_id, $start_date, $end_date);
    $stmt->execute();
    $metrics_result = $stmt->get_result()->fetch_assoc();
    
    $total = $metrics_result['total'] ?: 1; // Avoid division by zero
    $metrics = [
        'high_risk_rate' => round(($metrics_result['high_risk'] / $total) * 100),
        'moderate_risk_rate' => round(($metrics_result['moderate_risk'] / $total) * 100),
        'low_risk_rate' => round(($metrics_result['low_risk'] / $total) * 100),
        'avg_risk_score' => round($metrics_result['avg_risk_score'])
    ];
    
    echo json_encode([
        'success' => true, 
        'data' => [
            'labels' => $labels,
            'high_risk' => $high_risk,
            'moderate_risk' => $moderate_risk,
            'low_risk' => $low_risk,
            'avg_scores' => $avg_scores
        ],
        'metrics' => $metrics
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error retrieving chart data: ' . $e->getMessage()]);
}
?>
