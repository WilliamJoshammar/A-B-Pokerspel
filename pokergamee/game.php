<?php
require_once 'db.php';
require_once 'poker_logic.php';
requireLogin();

$user = currentUser();
$db = getDB();
$roomId = (int)($_GET['room'] ?? 0);

if (!$roomId) { header('Location: /pokerGame/lobby.php'); exit; }

$room = $db->prepare("SELECT * FROM rooms WHERE id = ?");
$room->execute([$roomId]);
$room = $room->fetch();
if (!$room) { header('Location: /pokerGame/lobby.php'); exit; }

// Lägg till spelare om inte redan med
$existing = $db->prepare("SELECT * FROM room_players WHERE room_id = ? AND user_id = ?");
$existing->execute([$roomId, $user['id']]);
$existing = $existing->fetch();

if (!$existing) {
    $takenSeats = $db->prepare("SELECT seat FROM room_players WHERE room_id = ? AND is_active = 1");
    $takenSeats->execute([$roomId]);
    $taken = array_column($takenSeats->fetchAll(), 'seat');
    $allSeats = range(0, $room['max_players'] - 1);
    $free = array_diff($allSeats, $taken);
    if (empty($free)) { header('Location: /pokerGame/lobby.php'); exit; }
    $seat = min($free);
    $db->prepare("INSERT INTO room_players (room_id, user_id, seat, chips_in_game) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE is_active=1, chips_in_game=?")
       ->execute([$roomId, $user['id'], $seat, $user['chips'], $user['chips']]);
} else {
    $db->prepare("UPDATE room_players SET is_active=1 WHERE room_id=? AND user_id=?")
       ->execute([$roomId, $user['id']]);
}

require_once 'templates/game.html.php';
?>
