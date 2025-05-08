<?php 
require_once '../config.php'; 
session_start(); 

header('Content-Type: application/json'); 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') { 
    echo json_encode(['error' => 'Unauthorized access']); 
    exit; 
}

$user_id = $_SESSION['user_id']; 

$page = isset($_GET['page']) ? intval($_GET['page']) : 1; 
$limit = 10; // Number of appointments per page 
$offset = ($page - 1) * $limit; 

// Get search filters 
$search = isset($_GET['search']) ? trim($_GET['search']) : ''; 
$status = isset($_GET['status']) ? trim($_GET['status']) : ''; 
$date_filter = isset($_GET['date']) ? intval($_GET['date']) : 0; 

// Build the query 
$query = " 
    SELECT a.id, a.counsellor_id, a.appointment_date, a.start_time, a.end_time, a.status, a.notes, 
           c.id as counsellor_id, u.name as counsellor_name, c.profile_photo 
    FROM appointments a 
    JOIN counsellors c ON a.counsellor_id = c.id 
    JOIN users u ON c.user_id = u.id 
    WHERE a.client_id = ? 
"; 
$params = [$user_id]; 
$types = "i"; 

// Add status filter 
if ($status) { 
    $query .= " AND a.status = ?"; 
    $params[] = $status; 
    $types .= "s"; 
} 

// Add date filter 
if ($date_filter > 0) { 
    $date_limit = date('Y-m-d', strtotime("-$date_filter days")); 
    $query .= " AND a.appointment_date >= ?"; 
    $params[] = $date_limit; 
    $types .= "s"; 
} 

// Add search filter 
if ($search) { 
    $search_param = "%$search%"; 
    $query .= " AND (u.name LIKE ? OR a.notes LIKE ?)"; 
    $params[] = $search_param; 
    $params[] = $search_param; 
    $types .= "ss"; 
} 

// Only show past appointments or cancelled ones 
$query .= " 
    AND (a.status IN ('completed', 'cancelled', 'no-show') 
    OR (a.appointment_date < CURDATE() 
    OR (a.appointment_date = CURDATE() AND a.end_time < CURTIME())))
"; 

// Add ordering 
$query .= " ORDER BY a.appointment_date DESC, a.start_time DESC"; 

// Get total count for pagination 
$count_query = str_replace("SELECT a.id, a.counsellor_id, a.appointment_date", "SELECT COUNT(*)", $query); 

$stmt = $conn->prepare($count_query); 
$stmt->bind_param($types, ...$params); 
$stmt->execute(); 
$result = $stmt->get_result(); 
$total_row = $result->fetch_row(); 
$total = $total_row[0]; // Corrected to use the correct index

$stmt->close(); 

// Add limit and offset 
$query .= " LIMIT ? OFFSET ?"; 
$params[] = $limit; 
$params[] = $offset; 
$types .= "ii"; 

// Execute the main query 
$stmt = $conn->prepare($query); 
$stmt->bind_param($types, ...$params); 
$stmt->execute(); 
$result = $stmt->get_result(); 
$appointments = []; 

while ($row = $result->fetch_assoc()) { 
    $appointments[] = $row; 
} 

$stmt->close(); 

// Calculate if there are more results 
$has_more = ($offset + count($appointments)) < $total; 

echo json_encode([ 
    'appointments' => $appointments, 
    'has_more' => $has_more, 
    'total' => $total, 
    'page' => $page, 
    'pages' => ceil($total / $limit) 
]);
?>
