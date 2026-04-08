<?php
// -------------------------------------------------------
// db/admin-helpers.php
// Shared helpers for reading/writing admin credential files.
//
// _admin.json format (array of admin entries):
//   [{"user":"admin","pass":"$2y$...","email":"x@y.com","mustChange":true}, ...]
//
// Backward compat: single-object format (legacy) is auto-wrapped in an array.
// -------------------------------------------------------

function loadAdminCreds(string $file): array {
  if (!file_exists($file)) return [];
  $data = json_decode(file_get_contents($file), true);
  if (!is_array($data)) return [];
  // Legacy single-object format: {user, pass, ...} → wrap in array
  if (isset($data['user'])) return [$data];
  return $data;
}

function saveAdminCreds(string $file, array $admins): bool {
  return file_put_contents(
    $file,
    json_encode(array_values($admins), JSON_PRETTY_PRINT)
  ) !== false;
}

// Find admin entry by username (returns entry array or null)
function findAdmin(array $admins, string $user): ?array {
  foreach ($admins as $a) {
    if (($a['user'] ?? '') === $user) return $a;
  }
  return null;
}

// Find admin entry that matches username + password (returns entry array or null)
function findAdminByPassword(array $admins, string $user, string $pass): ?array {
  foreach ($admins as $a) {
    if (($a['user'] ?? '') === $user && password_verify($pass, $a['pass'] ?? '')) {
      return $a;
    }
  }
  return null;
}

// Apply $changes to the entry matching $user; pass null value to unset a key.
// Returns the updated array (does not write to disk).
function updateAdminEntry(array $admins, string $user, array $changes): array {
  foreach ($admins as &$a) {
    if (($a['user'] ?? '') === $user) {
      foreach ($changes as $k => $v) {
        if ($v === null) unset($a[$k]);
        else $a[$k] = $v;
      }
      break;
    }
  }
  unset($a);
  return $admins;
}
