<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Poker – Logga in</title>
    <link rel="stylesheet" href="/pokerGame/style.css">
</head>
<body class="auth-page">

<div class="auth-container">
    <div class="auth-logo">
        <span class="suit">♠</span>
        <span class="suit red">♥</span>
        <h1>POKER</h1>
        <span class="suit red">♦</span>
        <span class="suit">♣</span>
    </div>

    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="tabs">
        <button class="tab active" onclick="showTab('login')">Logga in</button>
        <button class="tab" onclick="showTab('register')">Registrera</button>
    </div>

    <form method="POST" id="login-form" class="auth-form">
        <input type="hidden" name="action" value="login">
        <input type="text" name="username" placeholder="Användarnamn" required autocomplete="username">
        <input type="password" name="password" placeholder="Lösenord" required autocomplete="current-password">
        <button type="submit" class="btn-primary">Logga in</button>
    </form>

    <form method="POST" id="register-form" class="auth-form hidden">
        <input type="hidden" name="action" value="register">
        <input type="text" name="username" placeholder="Välj användarnamn" required>
        <input type="password" name="password" placeholder="Välj lösenord (min 4 tecken)" required>
        <button type="submit" class="btn-primary">Skapa konto</button>
    </form>
</div>

<script src="/pokerGame/js/login.js"></script>
</body>
</html>
