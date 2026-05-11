// lobby

<?php
require_once 'db.php';
requireLogin();

$user = currentUser();
$db = getDB();

$rooms = $db->query("
    SELECT r.*,
           COUNT(rp.id) as player_count
    FROM rooms r
    LEFT JOIN room_players rp ON r.id = rp.room_id AND rp.is_active = 1
    GROUP BY r.id
")->fetchAll();

require_once 'templates/lobby.html.php';
?>
