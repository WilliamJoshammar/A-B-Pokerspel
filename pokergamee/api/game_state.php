// Spelstatus

<?php
require_once '../db.php';
require_once '../poker_logic.php';
header('Content-Type: application/json');

$roomId = (int)($_GET['room'] ?? 0);
$userId = (int)($_GET['user'] ?? 0);
if (!$roomId || !$userId) { echo json_encode(['error'=>'Saknar parametrar']); exit; }

$db = getDB();

// Spelstatus
$gs = $db->prepare("SELECT * FROM game_state WHERE room_id = ?");
$gs->execute([$roomId]);
$gs = $gs->fetch();

// Spelare
$players = $db->prepare("
    SELECT rp.*, u.username, ph.bet_amount as bet, ph.has_folded, ph.cards
    FROM room_players rp
    JOIN users u ON u.id = rp.user_id
    LEFT JOIN player_hands ph ON ph.room_id = rp.room_id AND ph.user_id = rp.user_id
    WHERE rp.room_id = ? AND rp.is_active = 1
    ORDER BY rp.seat
");
$players->execute([$roomId]);
$players = $players->fetchAll();

// Mina kort
$myHand = $db->prepare("SELECT cards FROM player_hands WHERE room_id = ? AND user_id = ?");
$myHand->execute([$roomId, $userId]);
$myHand = $myHand->fetch();
$myCards = $myHand ? json_decode($myHand['cards'], true) : [];

// Hitta min info
$myPlayer = null;
$myBet = 0;
foreach ($players as $p) {
    if ($p['user_id'] == $userId) { $myPlayer = $p; $myBet = $p['bet'] ?? 0; break; }
}

// Vem är nuvarande spelare?
$currentPlayerId = null;
if ($gs) {
    $currentSeat = $gs['current_player_seat'];
    foreach ($players as $p) {
        if ($p['seat'] == $currentSeat && !$p['has_folded']) {
            $currentPlayerId = $p['user_id'];
            break;
        }
    }
}

// Handvärdering
$handRank = '';
$communityCards = $gs ? json_decode($gs['community_cards'], true) : [];
if (count($myCards) >= 2 && count($communityCards) > 0) {
    [, $handRank] = evaluateHand($myCards, $communityCards);
}

// Hitta vinnare vid showdown
$winner = null;
if ($gs && $gs['phase'] === 'showdown') {
    $best = -1;
    foreach ($players as $p) {
        if ($p['has_folded']) continue;
        $cards = json_decode($p['cards'] ?? '[]', true);
        [$rank] = evaluateHand($cards, $communityCards);
        if ($rank > $best) { $best = $rank; $winner = $p['username']; }
    }
}

// Är jag den första spelaren (skaparen)?
$isFirst = !empty($players) && $players[0]['user_id'] == $userId;

$playersOut = array_map(function($p) use ($gs) {
    $currentSeat = $gs ? $gs['current_player_seat'] : -1;
    return [
        'user_id'     => $p['user_id'],
        'username'    => $p['username'],
        'chips'       => $p['chips_in_game'],
        'bet'         => $p['bet'] ?? 0,
        'has_folded'  => (bool)$p['has_folded'],
        'seat'        => $p['seat'],
        'is_current'  => $p['seat'] == $currentSeat
    ];
}, $players);

echo json_encode([
    'phase'             => $gs ? $gs['phase'] : 'waiting',
    'pot'               => $gs ? $gs['pot'] : 0,
    'current_bet'       => $gs ? $gs['current_bet'] : 0,
    'community_cards'   => $communityCards,
    'my_cards'          => $myCards,
    'my_chips'          => $myPlayer ? $myPlayer['chips_in_game'] : 0,
    'my_bet'            => $myBet,
    'hand_rank'         => $handRank,
    'players'           => $playersOut,
    'player_count'      => count($players),
    'current_player_id' => $currentPlayerId,
    'is_first_player'   => $isFirst,
    'winner'            => $winner
]);
?>
