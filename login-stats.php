<?php
// Shared login-stats helper — included by any page that authenticates residents.

define('LOGIN_STATS_FILE', __DIR__ . '/credentials/login_stats.json');

function logLogin(string $building, string $username): void {
    $today  = date('Y-m-d');
    $cutoff = date('Y-m-d', strtotime('-12 months'));
    $data   = file_exists(LOGIN_STATS_FILE)
        ? (json_decode(file_get_contents(LOGIN_STATS_FILE), true) ?? [])
        : [];
    $data[$building][$username][$today] = ($data[$building][$username][$today] ?? 0) + 1;
    foreach ($data[$building][$username] as $date => $count) {
        if ($date < $cutoff) unset($data[$building][$username][$date]);
    }
    file_put_contents(LOGIN_STATS_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
