<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);
require_once '../config.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['data' => []]);
    exit;
}

$sql = "SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC";
$res = $conn->query($sql);
$data = [];
while ($row = $res->fetch_assoc()) {
    $row['role'] = ($row['role'] === 'staff') ? 'Counsellor' : ucfirst($row['role']);
    $row['actions'] = '
        <button class="btn btn-sm btn-info editUserBtn" data-id="'.$row['id'].'"><i class="fa fa-edit"></i></button>
        <button class="btn btn-sm btn-warning resetPwdBtn" data-id="'.$row['id'].'"><i class="fa fa-key"></i></button>
        <button class="btn btn-sm btn-secondary toggleUserBtn" data-id="'.$row['id'].'" data-action="'.($row['status']=='active'?'disable':'enable').'">'.($row['status']=='active'?'<i class="fa fa-ban"></i>':'<i class="fa fa-check"></i>').'</button>
        <button class="btn btn-sm btn-danger deleteUserBtn" data-id="'.$row['id'].'"><i class="fa fa-trash"></i></button>';
    $data[] = $row;
}
echo json_encode(['data' => $data]);
