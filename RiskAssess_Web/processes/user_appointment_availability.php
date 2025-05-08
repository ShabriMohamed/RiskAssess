<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$counselor_id = isset($_GET['counselor_id']) ? intval($_GET['counselor_id']) : 0;
$start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d');
$end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d', strtotime('+30 days'));

if (!$counselor_id) {
    echo json_encode([]);
    exit;
}

$events = [];

// Get regular weekly schedule
$stmt = $conn->prepare("
    SELECT id, counsellor_id, day_of_week, start_time, end_time, is_available 
    FROM counsellor_availability 
    WHERE counsellor_id = ?
");
$stmt->bind_param("i", $counselor_id);
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
    $isAvailable = false;
    
    // Reset result pointer
    $result->data_seek(0);
    
    while ($row = $result->fetch_assoc()) {
        if ($row['day_of_week'] == $dayOfWeek && $row['is_available'] == 1) {
            $isAvailable = true;
            $eventDate = $date->format('Y-m-d');
            
            // Add an availability event
            $events[] = [
                'title' => 'Available',
                'start' => $eventDate,
                'display' => 'background',
                'backgroundColor' => 'rgba(40, 167, 69, 0.2)',
                'classNames' => ['available-day']
            ];
            
            break;
        }
    }
    
    // If not available, add a non-available background event
    if (!$isAvailable) {
        $eventDate = $date->format('Y-m-d');
        $events[] = [
            'title' => 'Not Available',
            'start' => $eventDate,
            'display' => 'background',
            'backgroundColor' => 'rgba(220, 53, 69, 0.1)',
            'classNames' => ['unavailable-day']
        ];
    }
}

$stmt->close();

// Get exceptions (overrides regular schedule)
$stmt = $conn->prepare("
    SELECT id, counsellor_id, exception_date, is_available 
    FROM availability_exceptions 
    WHERE counsellor_id = ? AND exception_date BETWEEN ? AND ?
");
$stmt->bind_param("iss", $counselor_id, $start, $end);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $eventDate = $row['exception_date'];
    
    // Override the background event for this date
    if ($row['is_available'] == 0) {
        $events[] = [
            'title' => 'Not Available (Exception)',
            'start' => $eventDate,
            'display' => 'background',
            'backgroundColor' => 'rgba(220, 53, 69, 0.2)',
            'classNames' => ['unavailable-day', 'exception-day']
        ];
    } else {
        $events[] = [
            'title' => 'Available (Exception)',
            'start' => $eventDate,
            'display' => 'background',
            'backgroundColor' => 'rgba(40, 167, 69, 0.2)',
            'classNames' => ['available-day', 'exception-day']
        ];
    }
}

$stmt->close();

// Get existing appointments
$stmt = $conn->prepare("
    SELECT id, appointment_date, start_time, end_time, status
    FROM appointments
    WHERE counsellor_id = ? AND appointment_date BETWEEN ? AND ? AND status = 'scheduled'
");
$stmt->bind_param("iss", $counselor_id, $start, $end);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $eventDate = $row['appointment_date'];
    $eventStart = $eventDate . 'T' . $row['start_time'];
    $eventEnd = $eventDate . 'T' . $row['end_time'];
    
    // Add booked appointment event
    $events[] = [
        'title' => 'Booked',
        'start' => $eventStart,
        'end' => $eventEnd,
        'backgroundColor' => '#6c757d',
        'borderColor' => '#6c757d',
        'textColor' => '#ffffff',
        'classNames' => ['booked-slot']
    ];
}

$stmt->close();

echo json_encode($events);
