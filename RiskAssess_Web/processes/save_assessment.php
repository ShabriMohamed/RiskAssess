<?php

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log errors to a file
error_log("Assessment save attempt started: " . date('Y-m-d H:i:s'));


// processes/save_assessment.php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get the JSON data from the request
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

$client_id = $_SESSION['user_id'];

// Find a counsellor for this client (either assigned or random)
$stmt = $conn->prepare("
    SELECT c.id FROM counsellors c 
    JOIN appointments a ON c.id = a.counsellor_id 
    WHERE a.client_id = ? 
    GROUP BY c.id 
    ORDER BY COUNT(a.id) DESC 
    LIMIT 1
");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Assign to the counsellor who has had the most appointments with this client
    $counsellor_id = $result->fetch_assoc()['id'];
} else {
    // Assign to a random counsellor
    $stmt = $conn->prepare("SELECT id FROM counsellors ORDER BY RAND() LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'No counsellors available']);
        exit;
    }
    
    $counsellor_id = $result->fetch_assoc()['id'];
}

// Extract key factors based on questionnaire data
$key_factors = extractKeyFactors($data['questionnaire_data']);

// Generate recommendations based on risk level
$recommendations = generateRecommendations($data['risk_level'], $key_factors);

try {
    // Save assessment to database
    $stmt = $conn->prepare("
        INSERT INTO risk_assessments 
        (client_id, counsellor_id, risk_level, risk_score, questionnaire_data, key_factors, recommendations) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $questionnaire_json = json_encode($data['questionnaire_data']);
    $key_factors_json = json_encode($key_factors);
    
    $stmt->bind_param(
        "iisdsss", 
        $client_id, 
        $counsellor_id, 
        $data['risk_level'], 
        $data['risk_score'], 
        $questionnaire_json, 
        $key_factors_json, 
        $recommendations
    );
    
    
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        // Log the action
        $assessment_id = $conn->insert_id;
        $details = json_encode([
            'risk_level' => $data['risk_level'],
            'risk_score' => $data['risk_score'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        $stmt = $conn->prepare("
            INSERT INTO audit_log (user_id, action, table_name, record_id, details)
            VALUES (?, 'Completed risk assessment', 'risk_assessments', ?, ?)
        ");
        $stmt->bind_param("iis", $client_id, $assessment_id, $details);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Assessment saved successfully']);
    } else {
        throw new Exception("Failed to save assessment: " . $conn->error);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    error_log("Error saving assessment: " . $e->getMessage());

}


function extractKeyFactors($data) {
    $key_factors = [];
    
    // High-risk indicators based on the model
    if (isset($data['Do you experience cravings for drugs?']) && $data['Do you experience cravings for drugs?'] === 'Yes') {
        $key_factors[] = 'Drug cravings';
    }
    
    if (isset($data['Which of the Following Best Describes Your Drug Use Frequency?'])) {
        $frequency = $data['Which of the Following Best Describes Your Drug Use Frequency?'];
        if ($frequency === 'Regularly' || $frequency === 'Weekly') {
            $key_factors[] = 'Regular drug use';
        }
    }
    
    if (isset($data['Have you ever experienced withdrawal symptoms from drug use?']) && 
        $data['Have you ever experienced withdrawal symptoms from drug use?'] === 'Yes') {
        $key_factors[] = 'Withdrawal symptoms';
    }
    
    if (isset($data['Is It Easy to Control Your Drug Usage?']) && 
        $data['Is It Easy to Control Your Drug Usage?'] === 'No, its not possible') {
        $key_factors[] = 'Difficulty controlling drug use';
    }
    
    if (isset($data['Do You Have Plans or Desire to Quit drugs (if you do use)?']) && 
        $data['Do You Have Plans or Desire to Quit drugs (if you do use)?'] === 'No, I dont plan to quit') {
        $key_factors[] = 'No desire to quit';
    }
    
    if (isset($data['How motivated are you to stay sober or reduce your substance use? (1-10 scale)']) && 
        intval($data['How motivated are you to stay sober or reduce your substance use? (1-10 scale)']) < 5) {
        $key_factors[] = 'Low motivation to stay sober';
    }
    
    if (isset($data['Have You Ever Had Suicidal Thoughts?']) && 
        $data['Have You Ever Had Suicidal Thoughts?'] === 'Yes') {
        $key_factors[] = 'Suicidal thoughts';
    }
    
    if (isset($data['Do Your Friends Influence Your Drug Use?']) && 
        $data['Do Your Friends Influence Your Drug Use?'] === 'Yes, often they do') {
        $key_factors[] = 'Peer influence';
    }
    
    // Check for mental health issues
    if (isset($data['Do You Experience Any of These Mental/Emotional Problems?'])) {
        $mental_issues = explode(';', $data['Do You Experience Any of These Mental/Emotional Problems?']);
        if (in_array('Depression', $mental_issues)) {
            $key_factors[] = 'Depression';
        }
        if (in_array('Anxiety', $mental_issues)) {
            $key_factors[] = 'Anxiety';
        }
        if (in_array('PTSD', $mental_issues)) {
            $key_factors[] = 'PTSD';
        }
    }
    
    // Check for substances used
    if (isset($data['Have you ever used any of the following substances? (Please select all that apply. If the drug youve used isnt listed, feel free to mention it.)'])) {
        $substances = explode(';', $data['Have you ever used any of the following substances? (Please select all that apply. If the drug youve used isnt listed, feel free to mention it.)']);
        $high_risk_substances = ['Cocaine', 'Heroin', 'Methamphetamine'];
        
        foreach ($high_risk_substances as $substance) {
            if (in_array($substance, $substances)) {
                $key_factors[] = $substance . ' use';
            }
        }
    }
    
    return $key_factors;
}

/**
 * Generate recommendations based on risk level and factors
 */
function generateRecommendations($risk_level, $key_factors) {
    $recommendations = '';
    
    switch ($risk_level) {
        case 'High':
            $recommendations = "Based on the assessment, this client shows high-risk indicators for substance use disorder. Immediate intervention is recommended. Consider scheduling a counseling session within the next 7 days. Evaluate for potential referral to specialized addiction treatment services.";
            
            // Add specific recommendations based on key factors
            if (in_array('Suicidal thoughts', $key_factors)) {
                $recommendations .= " URGENT: Client reported suicidal thoughts. Conduct immediate mental health evaluation and consider crisis intervention protocols.";
            }
            
            if (in_array('Withdrawal symptoms', $key_factors)) {
                $recommendations .= " Client reported withdrawal symptoms, which may require medical supervision. Consider medical evaluation for safe detoxification.";
            }
            break;
            
        case 'Moderate':
            $recommendations = "The client shows moderate risk for substance use issues. Recommend regular counseling sessions to address risk factors. Focus on harm reduction strategies and building motivation for change.";
            
            // Add specific recommendations based on key factors
            if (in_array('Peer influence', $key_factors)) {
                $recommendations .= " Social influence appears to be a significant factor. Consider social skills training and strategies for resisting peer pressure.";
            }
            
            if (in_array('Depression', $key_factors) || in_array('Anxiety', $key_factors)) {
                $recommendations .= " Mental health concerns were identified. Consider dual-diagnosis approach addressing both substance use and mental health.";
            }
            break;
            
        case 'Low':
            $recommendations = "The client shows low risk for substance use disorder. Provide education on substance use risks and healthy coping strategies. Periodic check-ins recommended to monitor for any changes in risk factors.";
            break;
            
        default:
            $recommendations = "Unable to determine specific recommendations. Please review the assessment data and provide personalized guidance.";
    }
    
    return $recommendations;
}
?>
