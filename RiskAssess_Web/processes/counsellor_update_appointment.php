<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$counsellor_user_id = $_SESSION['user_id'];
$appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if (!$appointment_id || !$action) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
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
    
    // Verify appointment belongs to this counsellor
    $stmt = $conn->prepare("
        SELECT a.*, u.email as client_email, u.name as client_name
        FROM appointments a
        JOIN users u ON a.client_id = u.id
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
    
    // Begin transaction
    $conn->begin_transaction();
    
    switch ($action) {
        case 'complete':
            // Update appointment status to completed
            $stmt = $conn->prepare("UPDATE appointments SET status = 'completed', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
            
            // Log the action
            $action_log = "Marked appointment as completed";
            $details = json_encode([
                'appointment_id' => $appointment_id,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, details) VALUES (?, ?, 'appointments', ?, ?)");
            $stmt->bind_param("isis", $counsellor_user_id, $action_log, $appointment_id, $details);
            $stmt->execute();
            break;
            
        case 'no_show':
            // Update appointment status to no-show
            $stmt = $conn->prepare("UPDATE appointments SET status = 'no-show', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
            
            // Log the action
            $action_log = "Marked appointment as no-show";
            $details = json_encode([
                'appointment_id' => $appointment_id,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, details) VALUES (?, ?, 'appointments', ?, ?)");
            $stmt->bind_param("isis", $counsellor_user_id, $action_log, $appointment_id, $details);
            $stmt->execute();
            break;
            
        case 'cancel':
            $reason = isset($_POST['reason']) ? trim($_POST['reason']) : 'No reason provided';
            $notify_client = isset($_POST['notify_client']) ? (int)$_POST['notify_client'] : 0;
            
            // Update appointment notes and status
            $notes = $appointment['notes'] ? $appointment['notes'] . "\n\nCancellation reason: " . $reason : "Cancellation reason: " . $reason;
            
            $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled', notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $notes, $appointment_id);
            $stmt->execute();
            
            // Log the action
            $action_log = "Cancelled appointment";
            $details = json_encode([
                'appointment_id' => $appointment_id,
                'reason' => $reason,
                'notify_client' => $notify_client,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, details) VALUES (?, ?, 'appointments', ?, ?)");
            $stmt->bind_param("isis", $counsellor_user_id, $action_log, $appointment_id, $details);
            $stmt->execute();
            
            
            if ($notify_client) {
                
                error_log("Notification would be sent to client: {$appointment['client_email']} about cancellation of appointment #{$appointment_id}");
            }
            break;
            
        case 'update_notes':
            $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
            
            // Update appointment notes
            $stmt = $conn->prepare("UPDATE appointments SET notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $notes, $appointment_id);
            $stmt->execute();
            
            // Log the action
            $action_log = "Updated appointment notes";
            $details = json_encode([
                'appointment_id' => $appointment_id,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, details) VALUES (?, ?, 'appointments', ?, ?)");
            $stmt->bind_param("isis", $counsellor_user_id, $action_log, $appointment_id, $details);
            $stmt->execute();
            break;
            
        default:
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->rollback();
    }
    
    error_log('Error in counsellor_update_appointment.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error updating appointment']);
}
