<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Validate inputs
$question1 = $_POST['question1'] ?? '';
$answer1 = $_POST['answer1'] ?? '';
$question2 = $_POST['question2'] ?? '';
$answer2 = $_POST['answer2'] ?? '';

if (empty($question1) || empty($answer1) || empty($question2) || empty($answer2)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Check if questions are the same
if ($question1 === $question2) {
    echo json_encode(['success' => false, 'message' => 'Please select different security questions']);
    exit;
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Hash the answers for security
    $hashed_answer1 = password_hash($answer1, PASSWORD_DEFAULT);
    $hashed_answer2 = password_hash($answer2, PASSWORD_DEFAULT);
    
    // Create security questions JSON
    $security_questions = json_encode([
        'question1' => $question1,
        'question2' => $question2,
        // Don't store the answers in plain text
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    // Check if user_security record exists
    $stmt = $conn->prepare("SELECT user_id FROM user_security WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing record
        $stmt = $conn->prepare("UPDATE user_security SET security_questions = ?, security_answer1 = ?, security_answer2 = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("sssi", $security_questions, $hashed_answer1, $hashed_answer2, $user_id);
    } else {
        // Create new record
        $stmt = $conn->prepare("INSERT INTO user_security (user_id, security_questions, security_answer1, security_answer2, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("isss", $user_id, $security_questions, $hashed_answer1, $hashed_answer2);
    }
    $stmt->execute();
    
    // Log the action
    $action = "Updated security questions";
    $details = json_encode(['timestamp' => date('Y-m-d H:i:s')]);
    
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, details) VALUES (?, ?, 'user_security', ?, ?)");
    $stmt->bind_param("isis", $user_id, $action, $user_id, $details);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Security questions updated successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error updating security questions: ' . $e->getMessage()]);
}
