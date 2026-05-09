<?php
require_once 'db.php';

$action = $_GET['action'] ?? '';

if ($action === 'logout') {
    // Ta bort spelare från aktiva rum
    if (isLoggedIn()) {
        $db = getDB();
        $db->prepare("UPDATE room_players SET is_active = 0 WHERE user_id = ?")
           ->execute([$_SESSION['user_id']]);
    }
    session_destroy();
    header('Location: /pokerGame/index.php');
    exit;
}

header('Location: /pokerGame/index.php');
exit;
?>
