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

// ===== AVANCERA SPELET =====
function advanceGame($db, $roomId) {
    $gs = $db->query("SELECT * FROM game_state WHERE room_id=$roomId")->fetch();
    if (!$gs) return;

    $stmt = $db->prepare("SELECT rp.*, ph.bet_amount, ph.has_folded, ph.has_acted
        FROM room_players rp
        LEFT JOIN player_hands ph ON ph.room_id=rp.room_id AND ph.user_id=rp.user_id
        WHERE rp.room_id=? AND rp.is_active=1 ORDER BY rp.seat");
    $stmt->execute([$roomId]);
    $players = $stmt->fetchAll();

    $active   = array_values(array_filter($players, fn($p) => !$p['has_folded']));
    $notActed = array_filter($active, fn($p) => !$p['has_acted']);

    // En spelare kvar → vinner direkt
    if (count($active) === 1) {
        $winner = $active[0];
        $pot    = (int)$gs['pot'];
        $db->prepare("UPDATE room_players SET chips_in_game=chips_in_game+? WHERE room_id=? AND user_id=?")
           ->execute([$pot, $roomId, $winner['user_id']]);
        $db->prepare("UPDATE game_state SET phase='showdown', pot=0 WHERE room_id=?")
           ->execute([$roomId]);
        $db->prepare("UPDATE rooms SET status='waiting' WHERE id=?")->execute([$roomId]);
        saveChips($db, $roomId);
        return;
    }

    if (empty($notActed)) {
        nextPhase($db, $roomId, $players, $gs);
    } else {
        // Hitta nästa spelare som inte agerat
        $currentSeat = $gs['current_player_seat'];
        $nextSeat    = null;
        $found       = false;
        $wrapped     = array_merge($players, $players);

        foreach ($wrapped as $p) {
            if ($found && !$p['has_folded'] && !$p['has_acted']) {
                $nextSeat = $p['seat'];
                break;
            }
            if ($p['seat'] == $currentSeat) $found = true;
        }

        if ($nextSeat !== null) {
            $db->prepare("UPDATE game_state SET current_player_seat=? WHERE room_id=?")
               ->execute([$nextSeat, $roomId]);
        }
    }
}

function nextPhase($db, $roomId, $players, $gs) {
    $phases = ['preflop' => 'flop', 'flop' => 'turn', 'turn' => 'river', 'river' => 'showdown'];
    $next      = $phases[$gs['phase']] ?? 'showdown';
    $deck      = json_decode($gs['deck'], true);
    $community = json_decode($gs['community_cards'], true);

    if ($next === 'flop') {
        $community = array_merge($community, dealCards($deck, 3));
    } elseif (in_array($next, ['turn', 'river'])) {
        $community[] = array_shift($deck);
    } elseif ($next === 'showdown') {
        doShowdown($db, $roomId, $players, $gs, $community, $deck);
        return;
    }

    // Återställ bets och acted
    $db->prepare("UPDATE player_hands SET bet_amount=0, has_acted=0 WHERE room_id=?")->execute([$roomId]);
    $active    = array_values(array_filter($players, fn($p) => !$p['has_folded']));
    $firstSeat = $active[0]['seat'];

    $db->prepare("UPDATE game_state SET phase=?, community_cards=?, deck=?, current_bet=0, current_player_seat=? WHERE room_id=?")
       ->execute([$next, json_encode($community), json_encode($deck), $firstSeat, $roomId]);
}

function doShowdown($db, $roomId, $players, $gs, $community, $deck) {
    $best   = -1;
    $winner = null;
    foreach ($players as $p) {
        if ($p['has_folded']) continue;
        $cards = json_decode(
            $db->query("SELECT cards FROM player_hands WHERE room_id=$roomId AND user_id={$p['user_id']}")->fetchColumn(),
            true
        );
        [$rank] = evaluateHand($cards, $community);
        if ($rank > $best) { $best = $rank; $winner = $p; }
    }

    if ($winner) {
        $pot = (int)$gs['pot'];
        $db->prepare("UPDATE room_players SET chips_in_game=chips_in_game+? WHERE room_id=? AND user_id=?")
           ->execute([$pot, $roomId, $winner['user_id']]);
    }

    $db->prepare("UPDATE game_state SET phase='showdown', community_cards=?, deck=?, pot=0, current_bet=0 WHERE room_id=?")
       ->execute([json_encode($community), json_encode($deck), $roomId]);
    $db->prepare("UPDATE rooms SET status='waiting' WHERE id=?")->execute([$roomId]);

    // FIX: Spara chips tillbaka till users så nästa runda börjar rätt
    saveChips($db, $roomId);
}

function saveChips($db, $roomId) {
    // Synka chips_in_game → users.chips efter varje runda
    $players = $db->query("SELECT user_id, chips_in_game FROM room_players WHERE room_id=$roomId AND is_active=1")->fetchAll();
    foreach ($players as $p) {
        $db->prepare("UPDATE users SET chips=? WHERE id=?")
           ->execute([$p['chips_in_game'], $p['user_id']]);
    }
    // Ta bort spelare som gått all-in och förlorat (0 chips)
    $db->prepare("UPDATE room_players SET is_active=0 WHERE room_id=? AND chips_in_game <= 0")
       ->execute([$roomId]);
}
?>
