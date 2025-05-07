<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$counsellor_id = isset($_GET['counsellor_id']) ? intval($_GET['counsellor_id']) : 0;
$start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d');
$end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d', strtotime('+30 days'));

if (!$counsellor_id) {
    echo json_encode([]);
    exit;
}

$events = [];

try {
    // Get regular weekly schedule
    $stmt = $conn->prepare("
        SELECT id, counsellor_id, day_of_week, start_time, end_time, is_available 
        FROM counsellor_availability 
        WHERE counsellor_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $counsellor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Convert start and end to DateTime objects
    $startDate = new DateTime($start);
    $endDate = new DateTime($end);
    $interval = new DateInterval('P1D');
    $dateRange = new DatePeriod($startDate, $interval, $endDate);

    // For each day in the range, check if there's a regular schedule
    foreach ($dateRange as $date) {
        $dayOfWeek = $date->format('w'); // 0 (Sunday) to 6 (Saturday)
        
        // Reset result pointer
        $result->data_seek(0);
        
        while ($row = $result->fetch_assoc()) {
            if ($row['day_of_week'] == $dayOfWeek) {
                $eventDate = $date->format('Y-m-d');
                $eventStart = $eventDate . 'T' . $row['start_time'];
                $eventEnd = $eventDate . 'T' . $row['end_time'];
                
                $title = $row['is_available'] == 1 ? 'Available' : 'Unavailable';
                $color = $row['is_available'] == 1 ? '#28a745' : '#dc3545';
                
                $events[] = [
                    'id' => $row['id'],
                    'title' => $title,
                    'start' => $eventStart,
                    'end' => $eventEnd,
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'textColor' => '#ffffff',
                    'type' => 'availability',
                    'counsellor_id' => $row['counsellor_id'],
                    'day_of_week' => $row['day_of_week'],
                    'is_available' => $row['is_available']
                ];
            }
        }
    }

    $stmt->close();

    // Get exceptions (overrides regular schedule)
    $stmt = $conn->prepare("
        SELECT id, counsellor_id, exception_date, start_time, end_time, is_available, reason 
        FROM availability_exceptions 
        WHERE counsellor_id = ? AND exception_date BETWEEN ? AND ?
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare exceptions failed: " . $conn->error);
    }
    
    $stmt->bind_param("iss", $counsellor_id, $start, $end);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $eventDate = $row['exception_date'];
        
        if ($row['is_available'] == 1 && $row['start_time'] && $row['end_time']) {
            $eventStart = $eventDate . 'T' . $row['start_time'];
            $eventEnd = $eventDate . 'T' . $row['end_time'];
            
            $events[] = [
                'id' => $row['id'],
                'title' => 'Exception: Available',
                'start' => $eventStart,
                'end' => $eventEnd,
                'backgroundColor' => '#4f8cff',
                'borderColor' => '#4f8cff',
                'textColor' => '#ffffff',
                'type' => 'exception',
                'counsellor_id' => $row['counsellor_id'],
                'is_available' => $row['is_available'],
                'reason' => $row['reason'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time']
            ];
        } else {
            // All-day unavailable exception
            $events[] = [
                'id' => $row['id'],
                'title' => 'Exception: Unavailable' . ($row['reason'] ? ' - ' . $row['reason'] : ''),
                'start' => $eventDate,
                'allDay' => true,
                'backgroundColor' => '#dc3545',
                'borderColor' => '#dc3545',
                'textColor' => '#ffffff',
                'type' => 'exception',
                'counsellor_id' => $row['counsellor_id'],
                'is_available' => $row['is_available'],
                'reason' => $row['reason']
            ];
        }
    }

    $stmt->close();

    // Get appointments
    $stmt = $conn->prepare("
        SELECT a.id, a.counsellor_id, a.client_id, a.appointment_date, a.start_time, a.end_time, a.status, 
               u.name as client_name
        FROM appointments a
        JOIN users u ON a.client_id = u.id
        WHERE a.counsellor_id = ? AND a.appointment_date BETWEEN ? AND ?
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare appointments failed: " . $conn->error);
    }
    
    $stmt->bind_param("iss", $counsellor_id, $start, $end);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $eventDate = $row['appointment_date'];
        $eventStart = $eventDate . 'T' . $row['start_time'];
        $eventEnd = $eventDate . 'T' . $row['end_time'];
        $color = '#4f8cff'; // Default blue for scheduled
        switch ($row['status']) {
            case 'completed': $color = '#28a745'; break;
            case 'cancelled': $color = '#dc3545'; break;
            case 'no-show': $color = '#6c757d'; break;
        }
        
        $events[] = [
            'id' => $row['id'],
            'title' => 'Appointment: ' . $row['client_name'],
            'start' => $eventStart,
            'end' => $eventEnd,
            'backgroundColor' => $color,
            'borderColor' => $color,
            'textColor' => '#ffffff',
            'type' => 'appointment',
            'counsellor_id' => $row['counsellor_id'],
            'client_id' => $row['client_id'],
            'client_name' => $row['client_name'],
            'status' => $row['status']
        ];
    }
    
    $stmt->close();
    
    echo json_encode($events);
} catch (Exception $e) {
    error_log("Error in events: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
    }
    