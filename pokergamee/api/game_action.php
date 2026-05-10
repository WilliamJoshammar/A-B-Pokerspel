<?php
require_once '../db.php';
require_once '../poker_logic.php';
header('Content-Type: application/json');

$roomId = (int)($_POST['room'] ?? 0);
$userId = (int)($_POST['user'] ?? 0);
$action = $_POST['action'] ?? '';
$amount = (int)($_POST['amount'] ?? 0);

if (!$roomId || !$userId) { echo json_encode(['error'=>'Saknar parametrar']); exit; }

$db = getDB();

// ===== STARTA SPEL =====
if ($action === 'start') {
    $players = $db->prepare("SELECT * FROM room_players WHERE room_id = ? AND is_active = 1 ORDER BY seat");
    $players->execute([$roomId]);
    $players = $players->fetchAll();

    if (count($players) < 2) { echo json_encode(['error'=>'Minst 2 spelare krävs']); exit; }

    // Återställ chips från users-tabellen om någon har 0
    foreach ($players as $p) {
        if ($p['chips_in_game'] <= 0) {
            $user = $db->query("SELECT chips FROM users WHERE id={$p['user_id']}")->fetch();
            $refill = max($user['chips'], 1000);
            $db->prepare("UPDATE room_players SET chips_in_game=? WHERE room_id=? AND user_id=?")
               ->execute([$refill, $roomId, $p['user_id']]);
        }
    }

    // Hämta om efter ev. uppdatering
    $stmt = $db->prepare("SELECT * FROM room_players WHERE room_id = ? AND is_active = 1 ORDER BY seat");
    $stmt->execute([$roomId]);
    $players = $stmt->fetchAll();

    $deck = createDeck();

    $db->prepare("DELETE FROM player_hands WHERE room_id = ?")->execute([$roomId]);
    foreach ($players as $p) {
        $cards = dealCards($deck, 2);
        $db->prepare("INSERT INTO player_hands (room_id, user_id, cards, bet_amount, has_folded, has_acted) VALUES (?,?,?,0,0,0)")
           ->execute([$roomId, $p['user_id'], json_encode($cards)]);
    }

    $room = $db->query("SELECT * FROM rooms WHERE id = $roomId")->fetch();
    $sb = $room['small_blind'];
    $bb = $room['big_blind'];

    $p0 = $players[0]; $p1 = $players[1];

    // Small blind
    $db->prepare("UPDATE player_hands SET bet_amount=?, has_acted=1 WHERE room_id=? AND user_id=?")
       ->execute([$sb, $roomId, $p0['user_id']]);
    $db->prepare("UPDATE room_players SET chips_in_game=chips_in_game-? WHERE room_id=? AND user_id=?")
       ->execute([$sb, $roomId, $p0['user_id']]);

    // Big blind
    $db->prepare("UPDATE player_hands SET bet_amount=? WHERE room_id=? AND user_id=?")
       ->execute([$bb, $roomId, $p1['user_id']]);
    $db->prepare("UPDATE room_players SET chips_in_game=chips_in_game-? WHERE room_id=? AND user_id=?")
       ->execute([$bb, $roomId, $p1['user_id']]);

    // Nästa spelare efter BB
    $nextSeat = isset($players[2]) ? $players[2]['seat'] : $players[0]['seat'];

    $db->prepare("DELETE FROM game_state WHERE room_id = ?")->execute([$roomId]);
    $db->prepare("INSERT INTO game_state (room_id, deck, community_cards, pot, current_player_seat, phase, dealer_seat, current_bet) VALUES (?,?,?,?,?,?,?,?)")
       ->execute([$roomId, json_encode($deck), '[]', $sb + $bb, $nextSeat, 'preflop', $players[0]['seat'], $bb]);

    $db->prepare("UPDATE rooms SET status='playing' WHERE id=?")->execute([$roomId]);
    echo json_encode(['ok' => true]); exit;
}
