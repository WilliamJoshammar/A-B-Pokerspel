<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Poker – <?= htmlspecialchars($room['name']) ?></title>
    <link rel="stylesheet" href="/pokerGame/style.css">
</head>
<body class="game-page">

<header class="top-bar">
    <a href="/pokerGame/lobby.php" class="btn-logout">← Lobby</a>
    <div class="logo-small">♠ <?= htmlspecialchars($room['name']) ?></div>
    <div class="user-info">
        <span>👤 <?= htmlspecialchars($user['username']) ?></span>
        <span class="chips">💰 <span id="chip-count">...</span></span>
    </div>
</header>

<div class="game-layout">

    <!-- Pokerbord -->
    <div class="game-area">
        <div class="poker-table">
            <div class="seats" id="seats-container"></div>
            <div class="table-center">
                <div class="pot-display">POT: <span id="pot-amount">0</span> chips</div>
                <div class="community-cards" id="community-cards"></div>
                <div class="phase-display" id="phase-display">Väntar på spelare...</div>
            </div>
        </div>

        <!-- Mina kort och knappar -->
        <div class="my-area">
            <div class="my-cards" id="my-cards"></div>
            <div class="hand-rank" id="hand-rank"></div>

            <div class="action-buttons" id="action-buttons" style="display:none">
                <button class="btn-fold" onclick="doAction('fold')">Fold</button>
                <button class="btn-check" id="btn-check" onclick="doAction('check')">Check</button>
                <button class="btn-call" id="btn-call" onclick="doAction('call')">Call</button>
                <div class="raise-area">
                    <input type="number" id="raise-amount" placeholder="Höjning" min="0" step="10">
                    <button class="btn-raise" onclick="doAction('raise')">Raise</button>
                </div>
            </div>

            <div id="waiting-msg" class="waiting-msg">Väntar på din tur...</div>
        </div>
    </div>

    <!-- Chatt -->
    <div class="chat-area">
        <div class="chat-header">💬 Rum-chatt</div>
        <div class="chat-messages" id="chat-messages"></div>
        <div class="chat-input-row">
            <input type="text" id="chat-input" placeholder="Skriv ett meddelande..."
                   maxlength="200" onkeypress="if(event.key==='Enter') sendChat()">
            <button onclick="sendChat()" class="btn-chat">➤</button>
        </div>
    </div>

</div>

<!-- Start-knapp -->
<div id="start-area" style="text-align:center;padding:10px;display:none">
    <button class="btn-primary" onclick="startGame()">Starta spel</button>
</div>

<!-- Skicka PHP-variabler till JavaScript -->
<script>
    const ROOM_ID    = <?= $roomId ?>;
    const MY_USER_ID = <?= $user['id'] ?>;
    const MY_USERNAME = '<?= addslashes($user['username']) ?>';
</script>
<script src="/pokerGame/js/game.js"></script>

</body>
</html>
