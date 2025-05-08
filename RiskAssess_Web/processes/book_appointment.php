<?php
require '../config.php';
$user_id = intval($_POST['user_id']);
$slot_id = intval($_POST['slot_id']);
$reason = trim($_POST['reason'] ?? '');

$res = $conn->query("SELECT is_booked FROM counsellor_slots WHERE id=$slot_id");
$row = $res->fetch_assoc();
if (!$row || $row['is_booked']) {
    echo json_encode(['success'=>false, 'message'=>'Slot is not available.']); exit;
}

$stmt = $conn->prepare("INSERT INTO appointments (slot_id, user_id, reason) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $slot_id, $user_id, $reason);
$stmt->execute();

$conn->query("UPDATE counsellor_slots SET is_booked=1 WHERE id=$slot_id");

echo json_encode(['success'=>true, 'message'=>'Appointment booked!']);
?>
