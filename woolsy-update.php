<?php
// -------------------------------------------------------
// woolsy-update.php
// Admin UI to build or update Woolsy's knowledge base.
//
// Setup mode  — no faqs/{building}_rules.md exists yet.
//               Reads all current docs, builds from scratch.
//               All sections shown as NEW checkboxes.
// Update mode — rules.md exists; doc changes detected.
//               Re-reads all current docs, regenerates fully.
//               Only changed/added/removed sections shown.
//
// Auth: reuses manage_auth_{building} session from admin.php.
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR',   __DIR__ . '/credentials/');
define('APPS_SCRIPT_URL',   'https://script.google.com/macros/s/AKfycbz6AnLGRWvm6ibJC-Mi4mc4JuNholXDcBIF6I04uTSH_ybe14xcRoMr4OIDDUBbOAaP/exec');
define('APPS_SCRIPT_TOKEN', 'wX7#mK2$pN9vQ4@hR6jT1!uL8eB3sF5c');
define('CREDITS_FILE',      __DIR__ . '/faqs/woolsy_credits.json');
define('RULES_DIR',         __DIR__ . '/faqs/');
define('CREDITS_DEFAULT_ALLOCATED', 1.0);
// Increment when the extraction prompt gains new topics/guidelines.
// Any building whose rules.md was built with an older version will be
// flagged in the admin card and woolsy-update.php for a rebuild.
define('PROMPT_VERSION', 3);

// -------------------------------------------------------
// Helper: read prompt version stamp from rules.md first line.
// Files written before versioning was added return 1.
// Files that don't exist yet return 0.
// -------------------------------------------------------
function getRulesVersion(string $file): int {
    if (!file_exists($file)) return 0;
    $fh   = fopen($file, 'r');
    $line = fgets($fh);
    fclose($fh);
    if (preg_match('/woolsy_prompt_version:\s*(\d+)/', $line, $m)) {
        return (int)$m[1];
    }
    return 1; // pre-versioning files
}

// -------------------------------------------------------
// Validate building + auth
// -------------------------------------------------------
$building = $_GET['building'] ?? '';
if (!$building || !preg_match('/^[a-zA-Z0-9_-]+$/', $building)) {
    die('<p style="color:red;">Invalid or missing building name.</p>');
}

$buildings = require __DIR__ . '/buildings.php';
if (!isset($buildings[$building])) {
    die('<p style="color:red;">Unknown building.</p>');
}

$adminCredFile = CREDENTIALS_DIR . $building . '_admin.json';
if (!file_exists($adminCredFile)) {
    header('Location: admin.php?building=' . urlencode($building));
    exit;
}

$sessionKey = 'manage_auth_' . $building;
if (empty($_SESSION[$sessionKey])) {
    header('Location: admin.php?building=' . urlencode($building));
    exit;
}

$publicFolderId = $buildings[$building]['publicFolderId'];

// -------------------------------------------------------
// Helper: call Apps Script (GET)
// -------------------------------------------------------
function callAppsScript(array $params, int $timeout = 60): array {
    $url = APPS_SCRIPT_URL . '?' . http_build_query(
        array_merge(['token' => APPS_SCRIPT_TOKEN], $params)
    );
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeout,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp ?: '{}', true) ?? ['error' => 'No response'];
}

// -------------------------------------------------------
// Helper: credit management
// -------------------------------------------------------
function loadCredits(): array {
    if (!file_exists(CREDITS_FILE)) return [];
    return json_decode(file_get_contents(CREDITS_FILE), true) ?? [];
}

function hasCredits(string $building): bool {
    $c = loadCredits()[$building] ?? ['allocated' => CREDITS_DEFAULT_ALLOCATED, 'used' => 0];
    return (float)($c['used'] ?? 0) < (float)($c['allocated'] ?? CREDITS_DEFAULT_ALLOCATED);
}

function deductCredits(string $building, float $cost): void {
    $credits = loadCredits();
    if (!isset($credits[$building])) {
        $credits[$building] = ['allocated' => CREDITS_DEFAULT_ALLOCATED, 'used' => 0.0];
    }
    $credits[$building]['used'] = round(($credits[$building]['used'] ?? 0) + $cost, 6);
    file_put_contents(CREDITS_FILE, json_encode($credits, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// -------------------------------------------------------
// Helper: section-level diff of rules.md content
// Splits on ## headings. Returns assoc [title => body].
// -------------------------------------------------------
function parseSections(string $md): array {
    $sections = [];
    $current  = null;
    $body     = '';
    foreach (explode("\n", $md) as $line) {
        if (preg_match('/^## (.+)/', $line, $m)) {
            if ($current !== null) {
                $sections[$current] = trim($body);
            }
            $current = trim($m[1]);
            $body    = '';
        } else {
            $body .= $line . "\n";
        }
    }
    if ($current !== null) {
        $sections[$current] = trim($body);
    }
    return $sections;
}

// Normalize a section heading for fuzzy matching.
// Handles Claude varying dash style (hyphen vs en-dash), capitalization, etc.
function normalizeKey(string $title): string {
    $t = mb_strtolower(trim($title));
    $t = preg_replace('/[\-\x{2013}\x{2014}\/]+/u', '-', $t); // unify dashes
    $t = preg_replace('/\s+/', ' ', $t);                        // collapse spaces
    return $t;
}

// Compute delta between old and new section maps.
// Matches sections by normalized heading so minor Claude rephrasing (dash
// style, capitalization) doesn't produce false NEW+REMOVED pairs.
// Returns array of ['type'=>NEW|CHANGED|REMOVED, 'title'=>..., 'content'=>..., 'old'=>...]
function computeDelta(array $old, array $new): array {
    // Build normalized-key → {original title, body} maps
    $oldNorm = [];
    foreach ($old as $title => $body) {
        $oldNorm[normalizeKey($title)] = ['title' => $title, 'body' => $body];
    }
    $newNorm = [];
    foreach ($new as $title => $body) {
        $newNorm[normalizeKey($title)] = ['title' => $title, 'body' => $body];
    }

    $delta = [];
    foreach ($newNorm as $key => $newItem) {
        if (!isset($oldNorm[$key])) {
            $delta[] = ['type' => 'NEW',     'title' => $newItem['title'], 'content' => $newItem['body']];
        } elseif ($oldNorm[$key]['body'] !== $newItem['body']) {
            $delta[] = ['type' => 'CHANGED', 'title' => $newItem['title'], 'content' => $newItem['body'], 'old' => $oldNorm[$key]['body']];
        }
        // unchanged → omitted from delta, always kept
    }
    foreach ($oldNorm as $key => $oldItem) {
        if (!isset($newNorm[$key])) {
            $delta[] = ['type' => 'REMOVED', 'title' => $oldItem['title'], 'old' => $oldItem['body']];
        }
    }
    return $delta;
}

// Rebuild final rules.md from accepted delta titles.
// accepted = set of section titles whose proposed change was accepted.
function rebuildRules(array $oldSections, array $newSections, array $delta, array $accepted): string {
    $acceptedSet = array_flip($accepted);
    $result      = [];

    // Walk through proposed sections in order
    foreach ($newSections as $title => $newBody) {
        $deltaType = null;
        foreach ($delta as $d) {
            if ($d['title'] === $title) { $deltaType = $d['type']; break; }
        }
        if ($deltaType === null) {
            // Unchanged — always include (new content === old content)
            $result[$title] = $newBody;
        } elseif ($deltaType === 'NEW') {
            // Include only if accepted
            if (isset($acceptedSet[$title])) {
                $result[$title] = $newBody;
            }
        } elseif ($deltaType === 'CHANGED') {
            // Use new content if accepted; keep old if not
            $result[$title] = isset($acceptedSet[$title]) ? $newBody : ($oldSections[$title] ?? $newBody);
        }
    }

    // Re-append REMOVED sections whose removal was rejected (not accepted)
    foreach ($delta as $d) {
        if ($d['type'] === 'REMOVED' && !isset($acceptedSet[$d['title']])) {
            $result[$d['title']] = $d['old'];
        }
    }

    // Assemble markdown
    $md = '';
    foreach ($result as $title => $body) {
        $md .= "## {$title}\n\n{$body}\n\n";
    }
    return trim($md);
}

// -------------------------------------------------------
// AJAX actions
// -------------------------------------------------------
$action = $_GET['action'] ?? '';

// --- listFiles ---
if ($action === 'listFiles') {
    header('Content-Type: application/json');
    $result = callAppsScript([
        'action'         => 'listDocFiles',
        'building'       => $building,
        'publicFolderId' => $publicFolderId,
    ]);
    echo json_encode($result);
    exit;
}

// --- probeFile: extract text from one PDF, cache in session ---
if ($action === 'probeFile') {
    header('Content-Type: application/json');
    set_time_limit(120);
    $fileId = $_GET['fileId'] ?? '';
    if (!$fileId || !preg_match('/^[a-zA-Z0-9_-]+$/', $fileId)) {
        echo json_encode(['error' => 'Invalid fileId']);
        exit;
    }
    $result   = callAppsScript(['action' => 'extractDocText', 'fileId' => $fileId], 90);
    $cacheKey = 'woolsy_text_' . $building . '_' . $fileId;
    if (!empty($result['text']) && ($result['readable'] ?? false)) {
        $_SESSION[$cacheKey] = $result['text'];
    } else {
        unset($_SESSION[$cacheKey]);
    }
    echo json_encode([
        'readable'  => $result['readable']  ?? false,
        'charCount' => $result['charCount'] ?? 0,
        'error'     => $result['error']     ?? null,
    ]);
    exit;
}

// --- process: call Claude, compute delta, return to client ---
if ($action === 'process' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    set_time_limit(300);

    if (!hasCredits($building)) {
        echo json_encode(['error' => 'Credits exhausted — contact SheepSite to top up.']);
        exit;
    }

    $body         = json_decode(file_get_contents('php://input'), true) ?? [];
    $files        = $body['files']        ?? [];
    $mode         = $body['mode']         ?? 'setup';
    $removedFiles = $body['removedFiles'] ?? [];

    // Collect texts from session cache
    $docTexts = [];
    foreach ($files as $file) {
        if (empty($file['readable'])) continue;
        $cacheKey = 'woolsy_text_' . $building . '_' . ($file['id'] ?? '');
        if (!empty($_SESSION[$cacheKey])) {
            $docTexts[] = [
                'name'   => $file['name'],
                'folder' => $file['folder'],
                'text'   => $_SESSION[$cacheKey],
            ];
        }
    }

    if (empty($docTexts)) {
        echo json_encode(['error' => 'No readable documents found. Cannot build knowledge base.']);
        exit;
    }

    $combinedText = '';
    foreach ($docTexts as $doc) {
        $combinedText .= "\n\n=== {$doc['folder']} / {$doc['name']} ===\n\n" . $doc['text'];
    }

    $removedNote = '';
    if (!empty($removedFiles)) {
        $removedNames = array_map(function($f) { return $f['folder'] . '/' . $f['name']; }, $removedFiles);
        $removedNote  = "\n\nNote: The following files have been removed and their content should be omitted:\n- " . implode("\n- ", $removedNames);
    }

    $rulesFile     = RULES_DIR . $building . '_rules.md';
    $existingRules = ($mode === 'update' && file_exists($rulesFile))
        ? file_get_contents($rulesFile)
        : '';

    if ($existingRules) {
        $systemPrompt = <<<PROMPT
You are updating the Woolsy knowledge base for the {$building} condominium association.

The current knowledge base is shown first. Below it are the current authoritative governing documents. Regenerate the COMPLETE, updated knowledge base from these documents.

Guidelines:
- Preserve ALL existing section headings exactly as written. Do NOT rename, merge, or drop any section.
- Add new sections for any topics covered in the documents that are not yet in the knowledge base. Use clear, descriptive headings that match the subject matter.
- After each rule or fact, add a source attribution in parentheses: (Source: Document Name)
- Be comprehensive but readable — this is a reference document used by an AI chatbot
- Skip boilerplate: recitals, "WHEREAS" clauses, signature blocks, and self-explanatory legal definitions
- Include fees, fines, deadlines, and contact information when present{$removedNote}

Return ONLY the Markdown content. No preamble, no explanation, no closing remarks.
PROMPT;
        $userContent = "CURRENT KNOWLEDGE BASE:\n\n{$existingRules}\n\n---\n\nCURRENT GOVERNING DOCUMENTS:\n{$combinedText}";
    } else {
        $systemPrompt = <<<PROMPT
You are building a knowledge base for Woolsy, an AI assistant for the {$building} condominium association.

The following governing documents have been extracted from PDFs. Distill them into a structured Markdown reference that Woolsy will use to answer resident questions accurately.

Guidelines:
- Extract every rule, policy, right, or obligation that an owner or resident would care about, need to follow, or might have a question about. If a resident could reasonably ask Woolsy about it, it belongs in the knowledge base.
- Create one section (## Heading) per distinct topic. Use clear headings that reflect the subject matter (e.g. Smoking, Parking, Pets, Right of Entry, Nuisance and Conduct, Alterations, Water Damage, etc.). Do not limit yourself to any predefined list — cover whatever the documents actually address.
- After each rule or fact, add a source attribution in parentheses: (Source: Document Name)
- Be comprehensive but readable — this is a reference document used by an AI chatbot
- Always capture unit boundary definitions and ownership/maintenance responsibility rules — even if written as legal definitions — because residents need to know what they own vs. what the association owns (e.g. doors, windows, floors, ceilings, patios, Florida rooms, pipes, wiring)
- Skip content that is not resident-facing: internal board procedures, legal recitals, "WHEREAS" clauses, signature blocks, and definitions that explain themselves
- Include fees, fines, deadlines, and contact information when present

Return ONLY the Markdown content. No preamble, no explanation, no closing remarks.
PROMPT;
        $userContent = "GOVERNING DOCUMENTS:\n{$combinedText}";
    }

    $apiKey = getenv('ANTHROPIC_API_KEY');
    if (!$apiKey) {
        echo json_encode(['error' => 'API key not configured on server.']);
        exit;
    }

    $payload = json_encode([
        'model'      => 'claude-sonnet-4-6',
        'max_tokens' => 8000,
        'system'     => $systemPrompt,
        'messages'   => [['role' => 'user', 'content' => $userContent]],
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: '          . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_TIMEOUT        => 240,
    ]);
    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        echo json_encode(['error' => 'API request failed: ' . $curlErr]);
        exit;
    }

    $data     = json_decode($response, true);
    $proposed = '';
    foreach ($data['content'] ?? [] as $block) {
        if ($block['type'] === 'text') { $proposed = $block['text']; break; }
    }

    if (!$proposed) {
        echo json_encode(['error' => 'No response from Claude. Check API key and credits.']);
        exit;
    }

    // Deduct credits
    $inputTokens  = (int)($data['usage']['input_tokens']  ?? 0);
    $outputTokens = (int)($data['usage']['output_tokens'] ?? 0);
    $creditsUsed  = round(($inputTokens * 0.000003) + ($outputTokens * 0.000015), 6);
    deductCredits($building, $creditsUsed);

    // Compute delta
    $newSections = parseSections($proposed);
    $oldSections = $existingRules ? parseSections($existingRules) : [];
    // Setup mode: every section is "NEW"
    $delta = $existingRules
        ? computeDelta($oldSections, $newSections)
        : array_map(function($title, $body) {
            return ['type' => 'NEW', 'title' => $title, 'content' => $body];
          }, array_keys($newSections), array_values($newSections));

    // Store sections in session for save step
    $_SESSION['woolsy_new_sections_' . $building] = $newSections;
    $_SESSION['woolsy_old_sections_' . $building] = $oldSections;
    $_SESSION['woolsy_delta_'        . $building] = $delta;

    echo json_encode(['delta' => $delta, 'creditsUsed' => $creditsUsed]);
    exit;
}

// --- save: apply accepted delta items, write rules.md ---
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $newSections = $_SESSION['woolsy_new_sections_' . $building] ?? [];
    $oldSections = $_SESSION['woolsy_old_sections_' . $building] ?? [];
    $delta       = $_SESSION['woolsy_delta_'        . $building] ?? [];

    if (empty($newSections)) {
        echo json_encode(['error' => 'Session expired. Please re-run the process.']);
        exit;
    }

    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $accepted = $body['accepted'] ?? [];

    $final     = rebuildRules($oldSections, $newSections, $delta, $accepted);
    $rulesFile = RULES_DIR . $building . '_rules.md';

    if (file_exists($rulesFile)) {
        copy($rulesFile, $rulesFile . '.bak');
    }
    $stamped = '<!-- woolsy_prompt_version: ' . PROMPT_VERSION . " -->\n" . $final;
    if (file_put_contents($rulesFile, $stamped) === false) {
        echo json_encode(['error' => 'Could not save file. Check that faqs/ folder is writable.']);
        exit;
    }

    // Stamp baseline in Apps Script
    callAppsScript([
        'action'         => 'stampBaseline',
        'building'       => $building,
        'publicFolderId' => $publicFolderId,
    ], 30);

    // Rebuild document index automatically after each save
    $indexResult = callAppsScript(['action' => 'buildDocIndex', 'publicFolderId' => $publicFolderId], 30);
    if (!empty($indexResult['sections'])) {
        $lines = ["DOCUMENT INDEX — {$building}", "Generated: " . date('F j, Y'), "", "PUBLIC DOCUMENTS", "================", ""];
        foreach ($indexResult['sections'] as $section) {
            $lines[] = $section['path'] . '/';
            foreach ($section['files'] as $file) { $lines[] = "  \u{2022} " . $file; }
            $lines[] = '';
        }
        file_put_contents(RULES_DIR . $building . '_docindex.txt', implode("\n", $lines));
    }

    // Clear session caches
    $prefix = 'woolsy_text_' . $building . '_';
    foreach (array_keys($_SESSION) as $key) {
        if (strpos($key, $prefix) === 0
            || $key === 'woolsy_new_sections_' . $building
            || $key === 'woolsy_old_sections_' . $building
            || $key === 'woolsy_delta_'        . $building) {
            unset($_SESSION[$key]);
        }
    }

    echo json_encode(['ok' => true]);
    exit;
}

// -------------------------------------------------------
// Page render
// -------------------------------------------------------
$rulesFile      = RULES_DIR . $building . '_rules.md';
$rulesVersion   = getRulesVersion($rulesFile);
$promptOutdated = ($rulesVersion > 0 && $rulesVersion < PROMPT_VERSION);
$mode           = !file_exists($rulesFile) ? 'setup' : ($promptOutdated ? 'rebuild' : 'update');
$changedFiles   = [];
$lastChecked    = '';

if ($mode === 'update' || $mode === 'rebuild') {
    $checkResult  = callAppsScript(['action' => 'docCheckResult', 'building' => $building], 15);
    $changedFiles = $checkResult['changes']   ?? [];
    $lastChecked  = $checkResult['checkedAt'] ?? '';
}

$existingRulesChars = (file_exists($rulesFile)) ? strlen(file_get_contents($rulesFile)) : 0;
$buildLabel = ucwords(str_replace(['_', '-'], ' ', $building));
$pageTitle  = match($mode) {
    'setup'   => 'Set Up Woolsy Knowledge Base',
    'rebuild' => 'Rebuild Woolsy Knowledge Base',
    default   => 'Update Woolsy Knowledge Base',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($buildLabel) ?> – <?= htmlspecialchars($pageTitle) ?></title>
  <style>
    body             { font-family: sans-serif; max-width: 820px; margin: 3rem auto; padding: 0 1rem; }
    .top-bar         { display: flex; align-items: baseline; gap: 1.5rem; margin-bottom: 1.5rem; }
    h1               { margin: 0; font-size: 1.4rem; }
    .back-link       { font-size: 0.9rem; color: #0070f3; text-decoration: none; white-space: nowrap; }
    .back-link:hover { text-decoration: underline; }
    .page-title      { font-size: 1.15rem; font-weight: bold; margin-bottom: 0.4rem; }
    .page-desc       { font-size: 0.9rem; color: #555; margin-bottom: 1.5rem; line-height: 1.5; }
    .muted           { color: #888; }
    .progress-msg    { font-size: 0.95rem; color: #555; margin-bottom: 1rem; line-height: 1.6; }
    .file-table      { width: 100%; border-collapse: collapse; font-size: 0.875rem; margin-bottom: 1rem; }
    .file-table th   { text-align: left; border-bottom: 2px solid #ddd; padding: 0.4rem 0.6rem; color: #333; }
    .file-table td   { border-bottom: 1px solid #eee; padding: 0.4rem 0.6rem; vertical-align: middle; }
    .file-name       { max-width: 320px; word-break: break-word; }
    .st-ok           { color: #1a7f37; }
    .st-warn         { color: #b45309; }
    .st-muted        { color: #aaa; }
    .badge           { font-size: 0.75rem; font-weight: bold; padding: 0.15rem 0.45rem; border-radius: 3px; }
    .badge-new       { background: #dcfce7; color: #166534; }
    .badge-modified  { background: #fef9c3; color: #713f12; }
    .badge-removed   { background: #fee2e2; color: #7f1d1d; }
    .warn-box        { padding: 0.65rem 0.9rem; background: #fffbeb; border: 1px solid #f59e0b;
                       border-radius: 4px; font-size: 0.875rem; color: #92400e; margin-bottom: 1rem; }
    .estimate-box    { padding: 0.65rem 0.9rem; background: #eff6ff; border: 1px solid #bfdbfe;
                       border-radius: 4px; font-size: 0.9rem; margin-bottom: 1.25rem; }
    .action-row      { display: flex; align-items: center; gap: 1.5rem; margin-top: 0.5rem; }
    .action-btn      { padding: 0.6rem 1.4rem; background: #0070f3; color: #fff; border: none;
                       border-radius: 4px; font-size: 0.95rem; cursor: pointer; }
    .action-btn:hover     { background: #005bb5; }
    .action-btn:disabled  { background: #ccc; cursor: not-allowed; }
    .action-btn-link { display: inline-block; padding: 0.5rem 1.2rem; background: #0070f3;
                       color: #fff; border-radius: 4px; font-size: 0.9rem; text-decoration: none; }
    .cancel-link          { color: #666; font-size: 0.9rem; text-decoration: none; }
    .cancel-link:hover    { text-decoration: underline; }
    /* Delta checklist */
    .review-header   { font-size: 0.95rem; margin-bottom: 0.75rem; }
    .review-header .credits-line { color: #888; font-size: 0.85rem; margin-top: 0.2rem; }
    .no-changes-box  { padding: 0.75rem 1rem; background: #f0faf0; border: 1px solid #86efac;
                       border-radius: 4px; color: #166534; font-size: 0.9rem; margin-bottom: 1rem; }
    .delta-list      { list-style: none; margin: 0 0 1.25rem 0; padding: 0; }
    .delta-item      { border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 0.6rem;
                       background: #fff; }
    .delta-item.type-new      { border-left: 4px solid #22c55e; }
    .delta-item.type-changed  { border-left: 4px solid #eab308; }
    .delta-item.type-removed  { border-left: 4px solid #ef4444; }
    .delta-item label { display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.7rem 0.9rem;
                        cursor: pointer; }
    .delta-item input[type=checkbox] { margin-top: 0.2rem; flex-shrink: 0; width: 1rem; height: 1rem; cursor: pointer; }
    .delta-item-body { flex: 1; min-width: 0; }
    .delta-title     { font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem; }
    .delta-preview   { font-size: 0.8rem; color: #555; line-height: 1.5; }
    .delta-old       { color: #9a3412; background: #fff7ed; border-radius: 3px;
                       padding: 0.25rem 0.4rem; margin-bottom: 0.2rem; }
    .delta-new       { color: #166534; background: #f0fdf4; border-radius: 3px;
                       padding: 0.25rem 0.4rem; }
    .delta-label     { font-size: 0.7rem; font-weight: 600; color: #888; margin-bottom: 0.15rem; }
    .error-box       { padding: 0.7rem 1rem; background: #fef2f2; border: 1px solid #fca5a5;
                       border-radius: 4px; color: #7f1d1d; font-size: 0.9rem; margin-top: 1rem; }
    .success-msg     { font-size: 1.1rem; color: #1a7f37; margin-bottom: 1rem; }
  </style>
</head>
<body>

<div class="top-bar">
  <a href="admin.php?building=<?= urlencode($building) ?>" class="back-link">← Admin</a>
  <h1><?= htmlspecialchars($buildLabel) ?> – Woolsy</h1>
</div>

<div class="page-title">🐑 <?= htmlspecialchars($pageTitle) ?></div>
<div class="page-desc">
  <?php if ($mode === 'setup'): ?>
    Woolsy will read the governing documents in your Public folder and build a knowledge base
    for answering resident questions. This only needs to be done once.
  <?php elseif ($mode === 'rebuild'): ?>
    The Woolsy extraction prompt has been updated (version <?= PROMPT_VERSION ?>) with new topic
    categories. A full rebuild will re-read all current documents and regenerate the knowledge base
    so Woolsy can answer questions about the newly added topics. Only changed sections will require
    your review.
  <?php else: ?>
    Changes were detected in your governing documents. Woolsy will re-read all current documents
    and regenerate the knowledge base.
    <?php if ($lastChecked): ?>
      <br><span class="muted">Last checked: <?= htmlspecialchars(date('M j, Y', strtotime($lastChecked))) ?></span>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- Step: probe (file listing + progress) -->
<div id="step-probe">
  <div class="progress-msg" id="progress-msg">⏳ Fetching document list… please wait</div>
  <table class="file-table" id="file-table" style="display:none">
    <thead>
      <tr>
        <th>Folder</th>
        <th>File</th>
        <th>Status</th>
        <th title="Uncheck to exclude a file from the knowledge base build">Include</th>
        <?php if ($mode === 'update'): ?><th>Change</th><?php endif; ?>
      </tr>
    </thead>
    <tbody id="file-tbody"></tbody>
  </table>
</div>

<!-- Step: ready to process -->
<div id="step-ready" style="display:none">
  <div id="readability-warning" class="warn-box" style="display:none"></div>
  <div class="estimate-box" id="estimate-box"></div>
  <div class="action-row">
    <button class="action-btn" id="process-btn" onclick="startProcess()">
      <?= match($mode) { 'setup' => 'Build Knowledge Base', 'rebuild' => 'Rebuild Knowledge Base', default => 'Update Knowledge Base' } ?>
    </button>
    <a href="admin.php?building=<?= urlencode($building) ?>" class="cancel-link">Cancel</a>
  </div>
</div>

<!-- Step: processing -->
<div id="step-processing" style="display:none">
  <div class="progress-msg">
    ⏳ Woolsy is reading your documents and building the knowledge base…<br>
    <span class="muted">This may take 2–3 minutes. &nbsp;<span id="elapsed-timer"></span></span>
  </div>
</div>

<!-- Step: review delta as checklist -->
<div id="step-review" style="display:none">
  <div class="review-header">
    <div id="review-intro"></div>
    <div class="credits-line" id="credits-used-line"></div>
  </div>
  <div id="no-changes-box" class="no-changes-box" style="display:none">
    ✅ No differences detected — the knowledge base is already up to date with the current documents.
  </div>
  <ul class="delta-list" id="delta-list"></ul>
  <div class="action-row">
    <button class="action-btn" id="save-btn" onclick="saveKnowledgeBase()">Save Knowledge Base</button>
    <a href="admin.php?building=<?= urlencode($building) ?>" class="cancel-link">Cancel</a>
  </div>
</div>

<!-- Step: saving -->
<div id="step-saving" style="display:none">
  <div class="progress-msg">⏳ Saving knowledge base…</div>
</div>

<!-- Step: done -->
<div id="step-done" style="display:none">
  <div class="success-msg">✅ Knowledge base saved successfully!</div>
  <p><a href="admin.php?building=<?= urlencode($building) ?>" class="action-btn-link">← Back to Admin</a></p>
</div>

<!-- Error display -->
<div id="error-box" class="error-box" style="display:none"></div>

<script>
const BUILDING           = <?= json_encode($building) ?>;
const MODE               = <?= json_encode($mode === 'rebuild' ? 'update' : $mode) ?>;
const CHANGED_FILES      = <?= json_encode($changedFiles) ?>;
const EXISTING_RULES_CHARS = <?= json_encode($existingRulesChars) ?>;
const BASE_URL           = 'woolsy-update.php?building=' + encodeURIComponent(BUILDING);

let allFiles = [];
let currentDelta = [];
let _elapsedInterval = null;

function startElapsedTimer() {
  const el = document.getElementById('elapsed-timer');
  if (!el) return;
  let secs = 0;
  el.textContent = '';
  _elapsedInterval = setInterval(function() {
    secs++;
    const m = Math.floor(secs / 60);
    const s = secs % 60;
    el.textContent = '(' + (m > 0 ? m + 'm ' : '') + s + 's elapsed)';
  }, 1000);
}

function stopElapsedTimer() {
  if (_elapsedInterval) { clearInterval(_elapsedInterval); _elapsedInterval = null; }
  const el = document.getElementById('elapsed-timer');
  if (el) el.textContent = '';
}

function toggleFileIncluded(fileId, checked) {
  const file = allFiles.find(f => f.id === fileId);
  if (file) file.included = checked;
  updateEstimate();
}

// -------------------------------------------------------
// Boot
// -------------------------------------------------------
document.addEventListener('DOMContentLoaded', startProbe);

// -------------------------------------------------------
// Step 1: Fetch file list
// -------------------------------------------------------
async function startProbe() {
  showStep('probe');
  setProgress('⏳ Fetching document list… please wait');

  let data;
  try {
    const resp = await fetch(BASE_URL + '&action=listFiles');
    data = await resp.json();
  } catch(e) {
    showError('Could not load document list. Please try again.');
    return;
  }
  if (data.error) { showError(data.error); return; }

  const inc   = (data.IncorporationDocs || []).map(f => ({...f, folder: 'IncorporationDocs', status: 'pending'}));
  const rules = (data.RulesDocs         || []).map(f => ({...f, folder: 'RulesDocs',         status: 'pending'}));
  allFiles = [...inc, ...rules];

  if (allFiles.length === 0) {
    showError('No documents found in IncorporationDocs or RulesDocs folders.');
    return;
  }

  document.getElementById('file-table').style.display = '';
  renderTable();
  await probeAllFiles();
}

// -------------------------------------------------------
// Step 2: Probe files one by one
// -------------------------------------------------------
async function probeAllFiles() {
  for (let i = 0; i < allFiles.length; i++) {
    const file = allFiles[i];
    setProgress('⏳ Checking documents… (' + (i + 1) + ' of ' + allFiles.length + ')');
    file.status = 'checking';
    updateRow(file);

    try {
      const resp = await fetch(BASE_URL + '&action=probeFile&fileId=' + encodeURIComponent(file.id));
      const data = await resp.json();
      file.readable  = data.readable === true;
      file.charCount = data.charCount || 0;
      file.status    = file.readable ? 'readable' : 'scanned';
    } catch(e) {
      file.readable  = false;
      file.charCount = 0;
      file.status    = 'error';
    }
    updateRow(file);
  }
  setProgress('');
  showReadySummary();
}

// -------------------------------------------------------
// Step 3: Summary + credit estimate
// -------------------------------------------------------
function showReadySummary() {
  const readable   = allFiles.filter(f => f.readable);
  const unreadable = allFiles.filter(f => !f.readable && f.status === 'scanned');
  const errors     = allFiles.filter(f => f.status === 'error');

  const warnParts = [];
  if (unreadable.length > 0) {
    const names = unreadable.map(f => f.name).join(', ');
    warnParts.push('<strong>' + unreadable.length + ' file' + (unreadable.length !== 1 ? 's' : '') +
      ' could not be read</strong> (possibly scanned images): ' + escHtml(names) +
      '. For best results, replace scanned PDFs with text-based versions. Woolsy will skip these.');
  }
  if (errors.length > 0) {
    warnParts.push(errors.length + ' file' + (errors.length !== 1 ? 's' : '') + ' could not be checked due to a connection error and will be skipped.');
  }
  const warnEl = document.getElementById('readability-warning');
  if (warnParts.length > 0) {
    warnEl.innerHTML = '⚠️ ' + warnParts.join(' ');
    warnEl.style.display = '';
  }

  if (readable.length === 0) {
    document.getElementById('process-btn').disabled = true;
  }

  updateEstimate();
  showStep('ready');
}

function updateEstimate() {
  const included = allFiles.filter(f => f.readable && f.included !== false);
  const excluded = allFiles.filter(f => f.readable && f.included === false);

  // Input = documents + existing rules.md (sent in update/rebuild mode)
  const docTokens    = included.reduce((s, f) => s + (f.charCount / 4), 0);
  const rulesTokens  = EXISTING_RULES_CHARS / 4;
  const inputTokens  = docTokens + rulesTokens;
  // Output ratio: Sonnet generates ~50% of input for a full KB; add 10% buffer
  const outputTokens = inputTokens * 0.55;
  const estimated    = (inputTokens / 1e6 * 3.00) + (outputTokens / 1e6 * 15.00);
  const estStr       = estimated < 0.01 ? '< 0.01' : estimated.toFixed(2);

  let fileSummary = included.length + ' of ' + allFiles.length + ' file' + (allFiles.length !== 1 ? 's' : '') + ' included';
  if (excluded.length > 0) {
    fileSummary += ' <span style="color:#b45309">(' + excluded.length + ' excluded)</span>';
  }

  document.getElementById('estimate-box').innerHTML =
    '<strong>Estimated cost:</strong> up to ~' + estStr + ' credits' +
    '&nbsp; <span class="muted">(' + fileSummary + ', ~' + Math.round(inputTokens).toLocaleString() + ' input tokens — approximate)</span>';

  document.getElementById('process-btn').disabled = (included.length === 0);
}

// -------------------------------------------------------
// Step 4: Process (call Claude via PHP)
// -------------------------------------------------------
async function startProcess() {
  showStep('processing');
  clearError();
  startElapsedTimer();

  const removedFiles   = CHANGED_FILES.filter(f => f.action === 'removed');
  const includedFiles  = allFiles.filter(f => f.included !== false);

  let data;
  try {
    const resp = await fetch(BASE_URL + '&action=process', {
      method:  'POST',
      headers: {'Content-Type': 'application/json'},
      body:    JSON.stringify({ files: includedFiles, mode: MODE, removedFiles: removedFiles })
    });
    data = await resp.json();
  } catch(e) {
    stopElapsedTimer();
    showError('Request failed. Please try again.');
    showStep('probe');
    return;
  }

  stopElapsedTimer();

  if (data.error) {
    showError(data.error);
    showStep('probe');
    return;
  }

  currentDelta = data.delta || [];
  document.getElementById('credits-used-line').textContent = 'Credits used: ' + data.creditsUsed;

  renderDelta(currentDelta);
  showStep('review');
}

// -------------------------------------------------------
// Render delta as checklist
// -------------------------------------------------------
function renderDelta(delta) {
  const list    = document.getElementById('delta-list');
  const introEl = document.getElementById('review-intro');
  const noChg   = document.getElementById('no-changes-box');

  list.innerHTML = '';

  if (delta.length === 0) {
    noChg.style.display = '';
    introEl.textContent = '';
    document.getElementById('save-btn').textContent = 'Save (no changes)';
    return;
  }

  noChg.style.display = 'none';

  if (MODE === 'setup') {
    introEl.innerHTML = 'Review the sections below. Uncheck any you want to <strong>exclude</strong> from the knowledge base. All are included by default.';
  } else {
    introEl.innerHTML = 'The following sections changed. Uncheck any you want to <strong>reject</strong> — rejected changes keep the previous version. All are accepted by default.';
  }

  const typeClass = { NEW: 'type-new', CHANGED: 'type-changed', REMOVED: 'type-removed' };
  const typeBadge = {
    NEW:     '<span class="badge badge-new">NEW</span>',
    CHANGED: '<span class="badge badge-modified">CHANGED</span>',
    REMOVED: '<span class="badge badge-removed">REMOVED</span>',
  };

  delta.forEach(function(item) {
    const li    = document.createElement('li');
    li.className = 'delta-item ' + (typeClass[item.type] || '');

    const preview = buildPreview(item);

    li.innerHTML =
      '<label>' +
        '<input type="checkbox" checked data-title="' + escAttr(item.title) + '">' +
        '<div class="delta-item-body">' +
          '<div class="delta-title">' + typeBadge[item.type] + escHtml(item.title) + '</div>' +
          '<div class="delta-preview">' + preview + '</div>' +
        '</div>' +
      '</label>';
    list.appendChild(li);
  });
}

function buildPreview(item) {
  const LIMIT = 220;
  function trunc(str) {
    if (!str) return '';
    const s = str.trim().replace(/\n+/g, ' ');
    return s.length > LIMIT ? escHtml(s.slice(0, LIMIT)) + '…' : escHtml(s);
  }

  if (item.type === 'NEW') {
    return trunc(item.content || '');
  }
  if (item.type === 'REMOVED') {
    return '<div class="delta-label">Will be removed:</div>' +
           '<div class="delta-old">' + trunc(item.old || '') + '</div>';
  }
  // CHANGED
  return '<div class="delta-label">Before:</div>' +
         '<div class="delta-old">'  + trunc(item.old     || '') + '</div>' +
         '<div class="delta-label" style="margin-top:0.3rem;">After:</div>' +
         '<div class="delta-new">' + trunc(item.content || '') + '</div>';
}

// -------------------------------------------------------
// Step 5: Save — send accepted titles to server
// -------------------------------------------------------
async function saveKnowledgeBase() {
  showStep('saving');
  clearError();

  // Collect checked titles
  const checkboxes = document.querySelectorAll('#delta-list input[type=checkbox]');
  const accepted   = [];
  checkboxes.forEach(function(cb) {
    if (cb.checked) accepted.push(cb.dataset.title);
  });

  let data;
  try {
    const resp = await fetch(BASE_URL + '&action=save', {
      method:  'POST',
      headers: {'Content-Type': 'application/json'},
      body:    JSON.stringify({ accepted: accepted })
    });
    data = await resp.json();
  } catch(e) {
    showError('Save failed. Please try again.');
    showStep('review');
    return;
  }

  if (data.error) {
    showError(data.error);
    showStep('review');
    return;
  }
  showStep('done');
}

// -------------------------------------------------------
// Table rendering
// -------------------------------------------------------
function renderTable() {
  const tbody = document.getElementById('file-tbody');
  tbody.innerHTML = '';
  allFiles.forEach(function(file) {
    const tr = document.createElement('tr');
    tr.id = 'row-' + file.id;
    tbody.appendChild(tr);
    updateRow(file);
  });
}

function updateRow(file) {
  const tr = document.getElementById('row-' + file.id);
  if (!tr) return;

  const statusHtml = {
    pending:  '<span class="st-muted">⏳ Pending</span>',
    checking: '<span class="st-muted">⏳ Checking…</span>',
    readable: '<span class="st-ok">✅ Readable</span>',
    scanned:  '<span class="st-warn">⚠️ Possibly scanned</span>',
    error:    '<span class="st-warn">⚠️ Could not check</span>',
  }[file.status] || '';

  let changeBadge = '';
  if (MODE === 'update') {
    const chg = CHANGED_FILES.find(function(c) { return c.name === file.name && c.folder === file.folder; });
    if (chg) {
      const labels  = { new: 'NEW', modified: 'MODIFIED', removed: 'REMOVED' };
      const classes = { new: 'badge-new', modified: 'badge-modified', removed: 'badge-removed' };
      changeBadge = '<span class="badge ' + (classes[chg.action] || '') + '">' + (labels[chg.action] || chg.action.toUpperCase()) + '</span>';
    }
  }

  // Include checkbox — only for readable files; preserve current state
  let includeCell = '<td></td>';
  if (file.status === 'readable') {
    const checked = file.included !== false;
    includeCell = '<td style="text-align:center"><input type="checkbox" ' +
      (checked ? 'checked ' : '') +
      'onchange="toggleFileIncluded(\'' + escAttr(file.id) + '\', this.checked)" ' +
      'title="Include in knowledge base build"></td>';
  }

  tr.innerHTML =
    '<td>' + escHtml(file.folder) + '</td>' +
    '<td class="file-name">' + escHtml(file.name) + '</td>' +
    '<td>' + statusHtml + '</td>' +
    includeCell +
    (MODE === 'update' ? '<td>' + changeBadge + '</td>' : '');
}

// -------------------------------------------------------
// UI helpers
// -------------------------------------------------------
const STEPS = ['probe', 'ready', 'processing', 'review', 'saving', 'done'];

function showStep(step) {
  STEPS.forEach(function(s) {
    const el = document.getElementById('step-' + s);
    if (el) el.style.display = s === step ? '' : 'none';
  });
  if (step === 'ready') {
    document.getElementById('step-probe').style.display = '';
  }
}

function setProgress(msg) {
  const el = document.getElementById('progress-msg');
  if (el) el.innerHTML = msg;
}

function showError(msg) {
  const el = document.getElementById('error-box');
  el.textContent = '⚠️ ' + msg;
  el.style.display = '';
}

function clearError() {
  document.getElementById('error-box').style.display = 'none';
}

function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function escAttr(str) {
  return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
</script>
</body>
</html>
