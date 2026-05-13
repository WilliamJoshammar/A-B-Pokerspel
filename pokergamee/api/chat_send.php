<?php
// chat_send.php
require_once '../db.php';
header('Content-Type: application/json');

$roomId  = (int)($_POST['room'] ?? 0);
$userId  = (int)($_POST['user'] ?? 0);
$message = trim($_POST['message'] ?? '');

if (!$roomId || !$userId || !$message) { echo json_encode(['error'=>'Saknar data']); exit; }
if (strlen($message) > 200) $message = substr($message, 0, 200);

$db = getDB();
$db->prepare("INSERT INTO chat_messages (room_id, user_id, message) VALUES (?,?,?)")
   ->execute([$roomId, $userId, $message]);

echo json_encode(['ok'=>true]);
?>
