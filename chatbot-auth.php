<?php
// chatbot-auth.php — Session helpers for the Woolsy widget
//
// GET  ?action=whoami&building=X          — returns {loggedIn, username}
// GET  ?action=logout&building=X          — clears session, returns {ok}
// POST ?action=login&building=X           — authenticates, returns {ok, username} or {ok:false, error}

// CORS — widget runs on building sites (cross-origin); reflect Origin so session cookies work.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/login-stats.php';
session_start();

header('Content-Type: application/json');

$building   = trim($_REQUEST['building'] ?? '');
$action     = trim($_REQUEST['action']   ?? '');
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

if ($action === 'login') {
    $data     = json_decode(file_get_contents('php://input'), true) ?? [];
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';

    if (!$username || !$password) {
        echo json_encode(['ok' => false, 'error' => 'Please enter your username and password.']);
        exit;
    }

    $credFile = __DIR__ . '/credentials/' . $building . '.json';
    $users    = file_exists($credFile) ? json_decode(file_get_contents($credFile), true) : [];

    foreach ($users as $u) {
        if ($u['user'] === $username && password_verify($password, $u['pass'])) {
            $_SESSION[$sessionKey] = $username;
            logLogin($building, $username);
            echo json_encode(['ok' => true, 'username' => $username]);
            exit;
        }
    }

    echo json_encode(['ok' => false, 'error' => 'Incorrect username or password.']);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
