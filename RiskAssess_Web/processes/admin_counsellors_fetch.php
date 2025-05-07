<?php
header('Content-Type: application/json');
require_once '../config.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['data' => []]); exit;
}
$sql = "SELECT c.id, u.name, u.email, c.license_number, c.specialties, u.status, c.created_at, c.profile_photo
        FROM counsellors c
        JOIN users u ON c.user_id = u.id
        ORDER BY c.created_at DESC";
$res = $conn->query($sql);
$data = [];
while ($row = $res->fetch_assoc()) {
    $photo = $row['profile_photo'] ? '<img src="uploads/counsellors/'.htmlspecialchars($row['profile_photo']).'" class="counsellor-photo-thumb">' : '';
    $row['photo'] = $photo;
    $row['actions'] = '
        <button class="btn btn-sm btn-info editCounsellorBtn" data-id="'.$row['id'].'"><i class="fa fa-edit"></i></button>
        <button class="btn btn-sm btn-danger deleteCounsellorBtn" data-id="'.$row['id'].'"><i class="fa fa-trash"></i></button>';
    $data[] = $row;
}
echo json_encode(['data' => $data]);
