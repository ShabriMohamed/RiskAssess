<?php
require_once '../config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$counsellor_id = isset($_GET['counsellor_id']) ? intval($_GET['counsellor_id']) : 0;
$date = isset($_GET['date']) ? $_GET['date'] : '';

if (!$counsellor_id || !$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([]);
    exit;
}

// Get day of week (0 = Sunday, 6 = Saturday)
$dayOfWeek = date('w', strtotime($date));

// Check if there's an exception for this date
$stmt = $conn->prepare("
    SELECT is_available, start_time, end_time 
    FROM availability_exceptions 
    WHERE counsellor_id = ? AND exception_date = ?
");
$stmt->bind_param("is", $counsellor_id, $date);
$stmt->execute();
$result = $stmt->get_result();

$isException = false;
$exceptionAvailable = false;
$exceptionStartTime = null;
$exceptionEndTime = null;

if ($result->num_rows > 0) {
    $isException = true;
    $exception = $result->fetch_assoc();
    $exceptionAvailable = $exception['is_available'] == 1;
    $exceptionStartTime = $exception['start_time'];
    $exceptionEndTime = $exception['end_time'];
}
$stmt->close();

// If it's an exception and not available, return empty slots
if ($isException && !$exceptionAvailable) {
    echo json_encode([]);
    exit;
}

// Get regular schedule or use exception times
if ($isException && $exceptionAvailable) {
    // Use exception times
    $availableTimes = [
        [
            'start_time' => $exceptionStartTime,
            'end_time' => $exceptionEndTime
        ]
    ];
} else {
    // Get regular schedule for this day
    $stmt = $conn->prepare("
        SELECT start_time, end_time 
        FROM counsellor_availability 
        WHERE counsellor_id = ? AND day_of_week = ? AND is_available = 1
        ORDER BY start_time
    ");
    $stmt->bind_param("ii", $counsellor_id, $dayOfWeek);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $availableTimes = [];
    while ($row = $result->fetch_assoc()) {
        $availableTimes[] = $row;
    }
    $stmt->close();
    
    if (empty($availableTimes)) {
        echo json_encode([]);
        exit;
    }
}

// Get existing appointments for this date
$stmt = $conn->prepare("
    SELECT start_time, end_time 
    FROM appointments 
    WHERE counsellor_id = ? AND appointment_date = ? AND status != 'cancelled'
    ORDER BY start_time
");
$stmt->bind_param("is", $counsellor_id, $date);
$stmt->execute();
$result = $stmt->get_result();

$bookedTimes = [];
while ($row = $result->fetch_assoc()) {
    $bookedTimes[] = $row;
}
$stmt->close();

// Generate time slots (30 minutes each)
$slots = [];
$slotDuration = 30; // minutes

foreach ($availableTimes as $availableTime) {
    $startTime = strtotime($availableTime['start_time']);
    $endTime = strtotime($availableTime['end_time']);
    
    // Generate slots
    for ($time = $startTime; $time < $endTime; $time += $slotDuration * 60) {
        $slotStart = date('H:i:s', $time);
        $slotEnd = date('H:i:s', $time + $slotDuration * 60);
        
        // Check if slot is available (not booked)
        $isAvailable = true;
        foreach ($bookedTimes as $bookedTime) {
            $bookedStart = $bookedTime['start_time'];
            $bookedEnd = $bookedTime['end_time'];
            
            // Check for overlap
            if (($slotStart >= $bookedStart && $slotStart < $bookedEnd) || 
                ($slotEnd > $bookedStart && $slotEnd <= $bookedEnd) || 
                ($slotStart <= $bookedStart && $slotEnd >= $bookedEnd)) {
                $isAvailable = false;
                break;
            }
        }
        
        $slots[] = [
            'start_time' => $slotStart,
            'end_time' => $slotEnd,
            'available' => $isAvailable
        ];
    }
}

echo json_encode($slots);
