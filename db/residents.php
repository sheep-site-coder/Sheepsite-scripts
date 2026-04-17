<?php
// -------------------------------------------------------
// db/residents.php — MySQL equivalents of database-admin.gs
//
// All functions accept $building (string) and $data (array)
// and return PHP arrays in the same structure as the GAS
// JSON responses, so callers need minimal changes.
//
// Field name mapping (GAS column → MySQL column):
//   residents:  Unit # → unit, Full Time → full_time,
//               Resident → is_resident, Owner → is_owner, Renter → is_renter,
//               First Name → first_name, Last Name → last_name,
//               eMail → email, Phone #1 → phone1, Phone #2 → phone2,
//               Board → board_role
//   car_db:     Parking Spot → parking_spot, Car Make → make,
//               Car Model → model, Car Color → color, Lic # → plate
//   unit_info:  Insurance → insurance, Policy # → policy_num,
//               AC Replaced → ac_replaced, Water Tank → water_tank
//   emergency:  Condo Sitter → condo_sitter, Phone1 → phone1, Phone2 → phone2
// -------------------------------------------------------

require_once __DIR__ . '/db.php';

// -------------------------------------------------------
// Helpers: convert between GAS field names and DB columns
// -------------------------------------------------------

function residentToGas_(array $row): array {
  return [
    'Unit #'     => $row['unit']       ?? '',
    'Full Time'  => !empty($row['full_time']),
    'Resident'   => !empty($row['is_resident']),
    'Owner'      => !empty($row['is_owner']),
    'Renter'     => !empty($row['is_renter']),
    'First Name' => $row['first_name'] ?? '',
    'Last Name'  => $row['last_name']  ?? '',
    'eMail'      => $row['email']      ?? '',
    'Phone #1'   => $row['phone1']     ?? '',
    'Phone #2'   => $row['phone2']     ?? '',
    'Board'      => $row['board_role'] ?? '',
    '_id'        => $row['id']         ?? null,
  ];
}

function residentFromGas_(array $data): array {
  $db = [];
  if (isset($data['Unit #']))     $db['unit']        = trim($data['Unit #']);
  if (isset($data['Full Time']))  $db['full_time']   = $data['Full Time']  ? 1 : 0;
  if (isset($data['Resident']))   $db['is_resident'] = $data['Resident']   ? 1 : 0;
  if (isset($data['Owner']))      $db['is_owner']    = $data['Owner']      ? 1 : 0;
  if (isset($data['Renter']))     $db['is_renter']   = $data['Renter']     ? 1 : 0;
  if (isset($data['First Name'])) $db['first_name']  = trim($data['First Name']);
  if (isset($data['Last Name']))  $db['last_name']   = trim($data['Last Name']);
  if (isset($data['eMail']))      $db['email']       = trim($data['eMail']);
  if (isset($data['Phone #1']))   $db['phone1']      = trim($data['Phone #1']);
  if (isset($data['Phone #2']))   $db['phone2']      = trim($data['Phone #2']);
  if (isset($data['Board']))      $db['board_role']  = trim($data['Board']);
  // Renter is mutually exclusive with Owner, Resident, Full Time
  if (!empty($db['is_renter'])) {
    $db['is_owner']    = 0;
    $db['is_resident'] = 0;
    $db['full_time']   = 0;
  }
  return $db;
}

function carToGas_(array $row): array {
  return [
    'Unit #'       => $row['unit']         ?? '',
    'Parking Spot' => $row['parking_spot'] ?? '',
    'Car Make'     => $row['make']         ?? '',
    'Car Model'    => $row['model']        ?? '',
    'Car Color'    => $row['color']        ?? '',
    'Lic #'        => $row['plate']        ?? '',
    'Notes'        => $row['notes']        ?? '',
    '_id'          => $row['id']           ?? null,
  ];
}

function carFromGas_(array $data): array {
  $db = [];
  if (isset($data['Unit #']))       $db['unit']         = trim($data['Unit #']);
  if (isset($data['Parking Spot'])) $db['parking_spot'] = trim($data['Parking Spot']);
  if (isset($data['Car Make']))     $db['make']         = trim($data['Car Make']);
  if (isset($data['Car Model']))    $db['model']        = trim($data['Car Model']);
  if (isset($data['Car Color']))    $db['color']        = trim($data['Car Color']);
  if (isset($data['Lic #']))        $db['plate']        = trim($data['Lic #']);
  if (isset($data['Notes']))        $db['notes']        = trim($data['Notes']);
  return $db;
}

function unitInfoToGas_(array $row): array {
  return [
    'Unit #'      => $row['unit']       ?? '',
    'Insurance'   => $row['insurance']  ?? '',
    'Policy #'    => $row['policy_num'] ?? '',
    'AC Replaced' => $row['ac_replaced'] ?? '',
    'Water Tank'  => $row['water_tank']  ?? '',
    '_id'         => $row['id']          ?? null,
  ];
}

function unitInfoFromGas_(array $data): array {
  $db = [];
  if (isset($data['Unit #']))      $db['unit']        = trim($data['Unit #']);
  if (isset($data['Insurance']))   $db['insurance']   = trim($data['Insurance']);
  if (isset($data['Policy #']))    $db['policy_num']  = trim($data['Policy #']);
  if (isset($data['AC Replaced'])) $db['ac_replaced'] = trim($data['AC Replaced']) ?: null;
  if (isset($data['Water Tank']))  $db['water_tank']  = trim($data['Water Tank'])  ?: null;
  return $db;
}

function emergencyToGas_(array $row): array {
  return [
    'Unit #'       => $row['unit']         ?? '',
    'Condo Sitter' => !empty($row['condo_sitter']),
    'First Name'   => $row['first_name']   ?? '',
    'Last Name'    => $row['last_name']    ?? '',
    'eMail'        => $row['email']        ?? '',
    'Phone1'       => $row['phone1']       ?? '',
    'Phone2'       => $row['phone2']       ?? '',
    '_id'          => $row['id']           ?? null,
  ];
}

function emergencyFromGas_(array $data): array {
  $db = [];
  if (isset($data['Unit #']))       $db['unit']         = trim($data['Unit #']);
  if (isset($data['Condo Sitter'])) $db['condo_sitter'] = $data['Condo Sitter'] ? 1 : 0;
  if (isset($data['First Name']))   $db['first_name']   = trim($data['First Name']);
  if (isset($data['Last Name']))    $db['last_name']    = trim($data['Last Name']);
  if (isset($data['eMail']))        $db['email']        = trim($data['eMail']);
  if (isset($data['Phone1']))       $db['phone1']       = trim($data['Phone1']);
  if (isset($data['Phone2']))       $db['phone2']       = trim($data['Phone2']);
  return $db;
}

// -------------------------------------------------------
// listDatabase — all residents for a building
// Returns: ['rows' => [...]]
// -------------------------------------------------------
function dbListDatabase(string $building): array {
  $pdo  = getDB();
  $stmt = $pdo->prepare(
    'SELECT * FROM residents WHERE building = ? ORDER BY unit, last_name, first_name'
  );
  $stmt->execute([$building]);
  $rows = array_map('residentToGas_', $stmt->fetchAll());
  return ['rows' => $rows];
}

// -------------------------------------------------------
// getUnit — all data for one unit
// Returns: ['unit' => ..., 'residents' => [...], 'car' => {...}|null,
//           'unitInfo' => {...}|null, 'emergency' => [...]]
// -------------------------------------------------------
function dbGetUnit(string $building, string $unit): array {
  $pdo = getDB();

  $stmt = $pdo->prepare(
    'SELECT * FROM residents WHERE building = ? AND unit = ? ORDER BY is_owner DESC, last_name, first_name'
  );
  $stmt->execute([$building, $unit]);
  $residents = array_map('residentToGas_', $stmt->fetchAll());

  $stmt = $pdo->prepare('SELECT * FROM car_db WHERE building = ? AND unit = ?');
  $stmt->execute([$building, $unit]);
  $carRow = $stmt->fetch();
  $car = $carRow ? carToGas_($carRow) : null;

  $stmt = $pdo->prepare('SELECT * FROM unit_info WHERE building = ? AND unit = ?');
  $stmt->execute([$building, $unit]);
  $unitRow = $stmt->fetch();
  $unitInfo = $unitRow ? unitInfoToGas_($unitRow) : null;

  $stmt = $pdo->prepare(
    'SELECT * FROM emergency WHERE building = ? AND unit = ? ORDER BY last_name, first_name'
  );
  $stmt->execute([$building, $unit]);
  $emergency = array_map('emergencyToGas_', $stmt->fetchAll());

  return compact('unit', 'residents', 'car', 'unitInfo', 'emergency');
}

// -------------------------------------------------------
// getAllEmails — deduplicated emails from residents table
// Returns: ['emails' => [...]]
// -------------------------------------------------------
function dbGetAllEmails(string $building): array {
  $pdo  = getDB();
  $stmt = $pdo->prepare(
    'SELECT DISTINCT email FROM residents WHERE building = ? AND email != "" AND email LIKE "%@%"'
  );
  $stmt->execute([$building]);
  $emails = array_column($stmt->fetchAll(), 'email');
  return ['emails' => $emails];
}

// -------------------------------------------------------
// addResident — insert a new resident row
// -------------------------------------------------------
function dbAddResident(string $building, array $data): array {
  $fields = residentFromGas_($data);
  $fields['building'] = $building;

  $cols = implode(', ', array_keys($fields));
  $placeholders = implode(', ', array_fill(0, count($fields), '?'));
  $pdo  = getDB();
  $stmt = $pdo->prepare("INSERT INTO residents ($cols) VALUES ($placeholders)");
  $stmt->execute(array_values($fields));
  return ['ok' => true];
}

// -------------------------------------------------------
// editResident — update row matched by building + unit + first + last
// -------------------------------------------------------
function dbEditResident(string $building, array $data): array {
  $matchUnit  = trim($data['matchUnit']  ?? '');
  $matchFirst = trim($data['matchFirst'] ?? '');
  $matchLast  = trim($data['matchLast']  ?? '');

  $fields = residentFromGas_($data);
  unset($fields['unit']); // don't overwrite the key columns accidentally

  if (!$fields) return ['error' => 'No fields to update'];

  $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
  $pdo  = getDB();
  $stmt = $pdo->prepare(
    "UPDATE residents SET $sets
     WHERE building = ? AND unit = ? AND first_name = ? AND last_name = ?
     LIMIT 1"
  );
  $stmt->execute(array_merge(array_values($fields), [$building, $matchUnit, $matchFirst, $matchLast]));

  if ($stmt->rowCount() === 0) return ['error' => 'Row not found'];
  return ['ok' => true];
}

// -------------------------------------------------------
// deleteResident — remove row matched by building + unit + first + last
// -------------------------------------------------------
function dbDeleteResident(string $building, array $data): array {
  $matchUnit  = trim($data['matchUnit']  ?? '');
  $matchFirst = trim($data['matchFirst'] ?? '');
  $matchLast  = trim($data['matchLast']  ?? '');

  $pdo  = getDB();
  $stmt = $pdo->prepare(
    'DELETE FROM residents WHERE building = ? AND unit = ? AND first_name = ? AND last_name = ? LIMIT 1'
  );
  $stmt->execute([$building, $matchUnit, $matchFirst, $matchLast]);

  if ($stmt->rowCount() === 0) return ['error' => 'Row not found'];
  return ['ok' => true];
}

// -------------------------------------------------------
// editCar — upsert car_db row for a unit
// -------------------------------------------------------
function dbEditCar(string $building, array $data): array {
  $fields = carFromGas_($data);
  $unit   = $fields['unit'] ?? '';
  if (!$unit) return ['error' => 'Missing Unit #'];

  $fields['building'] = $building;

  $cols   = implode(', ', array_keys($fields));
  $placeholders = implode(', ', array_fill(0, count($fields), '?'));
  $updates = implode(', ', array_map(
    fn($k) => "$k = VALUES($k)",
    array_diff(array_keys($fields), ['building', 'unit'])
  ));

  $pdo  = getDB();
  $stmt = $pdo->prepare(
    "INSERT INTO car_db ($cols) VALUES ($placeholders)
     ON DUPLICATE KEY UPDATE $updates"
  );
  $stmt->execute(array_values($fields));
  return ['ok' => true];
}

// -------------------------------------------------------
// editUnitInfo — upsert unit_info row for a unit
// -------------------------------------------------------
function dbEditUnitInfo(string $building, array $data): array {
  $fields = unitInfoFromGas_($data);
  $unit   = $fields['unit'] ?? '';
  if (!$unit) return ['error' => 'Missing Unit #'];

  $fields['building'] = $building;

  $cols         = implode(', ', array_keys($fields));
  $placeholders = implode(', ', array_fill(0, count($fields), '?'));
  $updates = implode(', ', array_map(
    fn($k) => "$k = VALUES($k)",
    array_diff(array_keys($fields), ['building', 'unit'])
  ));

  $pdo  = getDB();
  $stmt = $pdo->prepare(
    "INSERT INTO unit_info ($cols) VALUES ($placeholders)
     ON DUPLICATE KEY UPDATE $updates"
  );
  $stmt->execute(array_values($fields));
  return ['ok' => true];
}

// -------------------------------------------------------
// addEmergency — insert a new emergency row
// -------------------------------------------------------
function dbAddEmergency(string $building, array $data): array {
  $fields = emergencyFromGas_($data);
  $fields['building'] = $building;

  $cols         = implode(', ', array_keys($fields));
  $placeholders = implode(', ', array_fill(0, count($fields), '?'));
  $pdo  = getDB();
  $stmt = $pdo->prepare("INSERT INTO emergency ($cols) VALUES ($placeholders)");
  $stmt->execute(array_values($fields));
  return ['ok' => true];
}

// -------------------------------------------------------
// editEmergency — update row matched by building + unit + first + last
// -------------------------------------------------------
function dbEditEmergency(string $building, array $data): array {
  $matchUnit  = trim($data['matchUnit']  ?? '');
  $matchFirst = trim($data['matchFirst'] ?? '');
  $matchLast  = trim($data['matchLast']  ?? '');

  $fields = emergencyFromGas_($data);
  unset($fields['unit']);

  if (!$fields) return ['error' => 'No fields to update'];

  $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
  $pdo  = getDB();
  $stmt = $pdo->prepare(
    "UPDATE emergency SET $sets
     WHERE building = ? AND unit = ? AND first_name = ? AND last_name = ?
     LIMIT 1"
  );
  $stmt->execute(array_merge(array_values($fields), [$building, $matchUnit, $matchFirst, $matchLast]));

  if ($stmt->rowCount() === 0) return ['error' => 'Row not found'];
  return ['ok' => true];
}

// -------------------------------------------------------
// deleteEmergency — remove row matched by building + unit + first + last
// -------------------------------------------------------
function dbDeleteEmergency(string $building, array $data): array {
  $matchUnit  = trim($data['matchUnit']  ?? '');
  $matchFirst = trim($data['matchFirst'] ?? '');
  $matchLast  = trim($data['matchLast']  ?? '');

  $pdo  = getDB();
  $stmt = $pdo->prepare(
    'DELETE FROM emergency WHERE building = ? AND unit = ? AND first_name = ? AND last_name = ? LIMIT 1'
  );
  $stmt->execute([$building, $matchUnit, $matchFirst, $matchLast]);

  if ($stmt->rowCount() === 0) return ['error' => 'Row not found'];
  return ['ok' => true];
}

// -------------------------------------------------------
// getEmailByUsername — look up a resident's email by their web username
// Mirrors the makeUsername() logic from manage-users.php so duplicate
// suffixes (jsmith2) resolve to the correct resident.
// Returns email string, or null if not found / no email on file.
// -------------------------------------------------------
function dbGetEmailByUsername(string $building, string $username): ?string {
  $pdo  = getDB();
  $stmt = $pdo->prepare(
    'SELECT first_name, last_name, email FROM residents WHERE building = ?
     ORDER BY unit, last_name, first_name'
  );
  $stmt->execute([$building]);
  $rows = $stmt->fetchAll();

  // Assign usernames in the same order/logic as makeUsername() + uniqueUsername()
  $seen = [];
  foreach ($rows as $row) {
    $base = strtolower(
      substr(preg_replace('/[^a-zA-Z]/', '', $row['first_name']), 0, 1)
      . preg_replace('/[^a-zA-Z]/', '', $row['last_name'])
    );
    if (!$base) continue;
    $seen[$base] = ($seen[$base] ?? 0) + 1;
    $uname = $seen[$base] === 1 ? $base : $base . $seen[$base];
    if ($uname === $username) {
      $email = trim($row['email'] ?? '');
      return $email !== '' ? $email : null;
    }
  }
  return null;
}

// -------------------------------------------------------
// sendTempPasswordEmail — send temp password via PHP mail (noreply@sheepsite.com)
// $isNewAccount=true uses the welcome body; false uses the reset body.
// Returns true if mail() accepted the message.
// -------------------------------------------------------
function sendTempPasswordEmail(string $to, string $username, string $tmpPw, string $loginURL, string $buildLabel, bool $isNewAccount = false): bool {
  $subject = 'Your temporary password – ' . $buildLabel;
  if ($isNewAccount) {
    $body = "A login account has been created for you.\n\n"
          . "    username --> $username   (note: all lower case)\n"
          . "    password --> $tmpPw\n\n"
          . "Please log in at the link below — you will be prompted to set a new password:\n"
          . "$loginURL\n\n"
          . "If you have any questions, please contact your building administrator.";
  } else {
    $body = "A password reset was requested for your account ($username).\n\n"
          . "Your new temporary password is:\n\n"
          . "    $tmpPw\n\n"
          . "Please log in at the link below — you will be prompted to set a new password:\n"
          . "$loginURL\n\n"
          . "If you did not request this, please contact your building administrator.";
  }
  $headers = implode("\r\n", [
    'From: SheepSite.com <noreply@sheepsite.com>',
    'Reply-To: noreply@sheepsite.com',
    'Content-Type: text/plain; charset=UTF-8',
  ]);
  return mail($to, $subject, $body, $headers);
}

// -------------------------------------------------------
// importResidents — bulk upsert from owners JSON (idempotent)
// Skips rows where first_name + last_name already exist for this building.
// Returns: ['ok' => true, 'added' => N, 'skipped' => N]
// -------------------------------------------------------
function dbImportResidents(string $building, array $owners): array {
  $pdo = getDB();

  // Load existing name combos
  $stmt = $pdo->prepare(
    'SELECT LOWER(CONCAT(first_name, "|", last_name)) AS key_ FROM residents WHERE building = ?'
  );
  $stmt->execute([$building]);
  $existing = array_column($stmt->fetchAll(), 'key_');
  $existing = array_flip($existing);

  $added = 0; $skipped = 0;
  foreach ($owners as $owner) {
    $first = trim($owner['firstName'] ?? '');
    $last  = trim($owner['lastName']  ?? '');
    if (!$first && !$last) continue;
    $key = strtolower($first . '|' . $last);
    if (isset($existing[$key])) { $skipped++; continue; }

    $stmt = $pdo->prepare(
      'INSERT INTO residents (building, unit, first_name, last_name, email)
       VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
      $building,
      trim($owner['unit']  ?? ''),
      $first, $last,
      trim($owner['email'] ?? ''),
    ]);
    $existing[$key] = true;
    $added++;
  }

  return ['ok' => true, 'added' => $added, 'skipped' => $skipped];
}

// -------------------------------------------------------
// res_requests — pending resident addition requests
//
// CREATE TABLE IF NOT EXISTS res_requests (
//   id           INT AUTO_INCREMENT PRIMARY KEY,
//   building     VARCHAR(50)  NOT NULL,
//   unit         VARCHAR(20)  NOT NULL,
//   submitted_by VARCHAR(100) NOT NULL,
//   req_type     VARCHAR(50)  NOT NULL,
//   first_name   VARCHAR(100),
//   last_name    VARCHAR(100),
//   email        VARCHAR(200),
//   phone1       VARCHAR(50),
//   phone2       VARCHAR(50),
//   full_time    TINYINT DEFAULT 0,
//   is_resident  TINYINT DEFAULT 0,
//   is_owner     TINYINT DEFAULT 0,
//   is_renter    TINYINT DEFAULT 0,
//   notes        TEXT,
//   submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
//   INDEX (building)
// );
// -------------------------------------------------------

function dbAddRequest(string $building, array $data): array {
  $pdo  = getDB();
  $stmt = $pdo->prepare(
    'INSERT INTO res_requests
       (building, unit, submitted_by, req_type, first_name, last_name,
        email, phone1, phone2, full_time, is_resident, is_owner, is_renter, notes)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
  );
  $stmt->execute([
    $building,
    trim($data['unit']         ?? ''),
    trim($data['submitted_by'] ?? ''),
    trim($data['req_type']     ?? ''),
    trim($data['first_name']   ?? ''),
    trim($data['last_name']    ?? ''),
    trim($data['email']        ?? ''),
    trim($data['phone1']       ?? ''),
    trim($data['phone2']       ?? ''),
    !empty($data['full_time'])   ? 1 : 0,
    !empty($data['is_resident']) ? 1 : 0,
    !empty($data['is_owner'])    ? 1 : 0,
    !empty($data['is_renter'])   ? 1 : 0,
    trim($data['notes']        ?? ''),
  ]);
  return ['ok' => true, 'id' => (int)$pdo->lastInsertId()];
}

function dbGetRequests(string $building): array {
  $pdo  = getDB();
  $stmt = $pdo->prepare(
    'SELECT * FROM res_requests WHERE building = ? ORDER BY submitted_at ASC'
  );
  $stmt->execute([$building]);
  return ['requests' => $stmt->fetchAll()];
}

function dbGetRequest(string $building, int $id): ?array {
  $pdo  = getDB();
  $stmt = $pdo->prepare('SELECT * FROM res_requests WHERE id = ? AND building = ?');
  $stmt->execute([$id, $building]);
  $row = $stmt->fetch();
  return $row ?: null;
}

function dbDeleteRequest(string $building, int $id): array {
  $pdo  = getDB();
  $stmt = $pdo->prepare('DELETE FROM res_requests WHERE id = ? AND building = ?');
  $stmt->execute([$id, $building]);
  return ['ok' => true];
}
