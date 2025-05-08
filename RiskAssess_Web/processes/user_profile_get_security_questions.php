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
    // Get security questions
    $stmt = $conn->prepare("SELECT security_questions FROM user_security WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => true, 'questions' => null]);
        exit;
    }
    
    $row = $result->fetch_assoc();
    $security_questions = json_decode($row['security_questions'], true);
    
    if (!$security_questions) {
        echo json_encode(['success' => true, 'questions' => null]);
        exit;
    }
    
    // Return only the questions, not the answers
    echo json_encode([
        'success' => true,
        'questions' => [
            'question1' => $security_questions['question1'] ?? '',
            'question2' => $security_questions['question2'] ?? ''
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error retrieving security questions: ' . $e->getMessage()]);
}
