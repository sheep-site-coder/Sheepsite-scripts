<?php
// chatbot-auth.php — Session helpers for the Woolsy widget
//
// GET ?action=whoami&building=X  — returns {loggedIn, username}
// GET ?action=logout&building=X  — clears session, returns {ok}

session_start();

header('Content-Type: application/json');

$building   = trim($_GET['building'] ?? '');
$action     = trim($_GET['action']   ?? '');
$sessionKey = 'private_auth_' . $building;

if (!$building) {
    echo json_encode(['error' => 'Missing building']);
    exit;
}

if ($action === 'whoami') {
    $loggedIn = !empty($_SESSION[$sessionKey]);
    echo json_encode([
        'loggedIn' => $loggedIn,
        'username' => $loggedIn ? (string) $_SESSION[$sessionKey] : '',
    ]);
    exit;
}

if ($action === 'logout') {
    unset($_SESSION[$sessionKey]);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
