<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Poker – Lobby</title>
    <link rel="stylesheet" href="/pokerGame/style.css">
</head>
<body class="lobby-page">

<header class="top-bar">
    <div class="logo-small">♠ POKER</div>
    <div class="user-info">
        <span>👤 <?= htmlspecialchars($user['username']) ?></span>
        <span class="chips">💰 <?= number_format($user['chips']) ?> chips</span>
        <a href="/pokerGame/auth.php?action=logout" class="btn-logout">Logga ut</a>
    </div>
</header>

<main class="lobby-main">
    <h2>Välj ett bord</h2>
    <div class="room-grid">
        <?php foreach ($rooms as $room): ?>
        <div class="room-card">
            <div class="room-name"><?= htmlspecialchars($room['name']) ?></div>
            <div class="room-info">
                <span>👥 <?= $room['player_count'] ?>/<?= $room['max_players'] ?></span>
                <span>Blinds: <?= $room['small_blind'] ?>/<?= $room['big_blind'] ?></span>
            </div>
            <div class="room-status <?= $room['status'] ?>">
                <?= $room['status'] === 'waiting' ? 'Väntar på spelare' : 'Pågår' ?>
            </div>
            <?php if ($room['player_count'] < $room['max_players']): ?>
                <a href="/pokerGame/game.php?room=<?= $room['id'] ?>" class="btn-primary">Gå med</a>
            <?php else: ?>
                <button class="btn-primary" disabled>Fullt</button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</main>

</body>
</html>
