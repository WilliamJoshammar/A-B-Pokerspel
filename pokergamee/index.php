<?php
require_once 'db.php';

if (isLoggedIn()) {
    header('Location: /pokerGame/lobby.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = $db->prepare("SELECT id, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            header('Location: /pokerGame/lobby.php');
            exit;
        } else {
            $error = 'Fel användarnamn eller lösenord.';
        }
    } elseif ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (strlen($username) < 3) {
            $error = 'Användarnamnet måste vara minst 3 tecken.';
        } elseif (strlen($password) < 4) {
            $error = 'Lösenordet måste vara minst 4 tecken.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
                $stmt->execute([$username, $hash]);
                $success = 'Konto skapat! Du kan nu logga in.';
            } catch (PDOException $e) {
                $error = 'Användarnamnet är redan taget.';
            }
        }
    }
}

require_once 'templates/login.html.php';
?>
