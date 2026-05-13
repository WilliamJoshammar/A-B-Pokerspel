<?php
// chat
require_once '../db.php';
header('Content-Type: application/json');

$roomId = (int)($_GET['room'] ?? 0);
$lastId = (int)($_GET['last_id'] ?? 0);

if (!$roomId) { echo json_encode([]); exit; }

$db = getDB();
$stmt = $db->prepare("
    SELECT cm.id, cm.user_id, cm.message, cm.sent_at, u.username
    FROM chat_messages cm
    JOIN users u ON u.id = cm.user_id
    WHERE cm.room_id = ? AND cm.id > ?
    ORDER BY cm.id ASC
    LIMIT 50
");
$stmt->execute([$roomId, $lastId]);
echo json_encode($stmt->fetchAll());
?>
