<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid exception ID']);
    exit;
}

// Check if there are appointments affected by this exception
$stmt = $conn->prepare("
    SELECT e.exception_date, e.is_available, COUNT(a.id) as appointment_count
    FROM availability_exceptions e
    LEFT JOIN appointments a ON a.counsellor_id = e.counsellor_id 
                            AND a.appointment_date = e.exception_date
                            AND a.status = 'scheduled'
    WHERE e.id = ?
    GROUP BY e.id
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    // If exception is "available" and there are appointments, warn before deleting
    if ($row['is_available'] == 1 && $row['appointment_count'] > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot delete: There are ' . $row['appointment_count'] . ' scheduled appointments on this date. Please reschedule or cancel them first.'
        ]);
        exit;
    }
}
$stmt->close();

// Delete the exception
$stmt = $conn->prepare("DELETE FROM availability_exceptions WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Exception deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error deleting exception: ' . $stmt->error]);
}
$stmt->close();
