<?php
// chatbot-admin.php — Admin assistant powered by the SheepSite Admin Manual
//
// POST JSON: {"building": "LyndhurstH", "question": "...", "history": [...]}
// Returns:   {"answer": "..."} or {"error": "..."}
//
// Auth: manage_auth_{building} session (admin or master).
// Cost absorbed as SheepSite operational — no credit deduction.
// Usage tracked in faqs/woolsy_admin_usage.json.

session_start();
header('Content-Type: application/json');

$buildings = require __DIR__ . '/buildings.php';

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$building = $body['building'] ?? '';
$question = trim($body['question'] ?? '');
$history  = $body['history']  ?? [];

if (!$question || !isset($buildings[$building])) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// Auth check — admin or master session required
$adminSessionKey = 'manage_auth_' . $building;
if (empty($_SESSION[$adminSessionKey])) {
    echo json_encode(['error' => 'Not authorised']);
    exit;
}

define('CREDITS_FILE',            __DIR__ . '/faqs/woolsy_credits.json');
define('CREDITS_DEFAULT_ALLOCATED', 1.0);

function loadCredits(): array {
    if (!file_exists(CREDITS_FILE)) return [];
    return json_decode(file_get_contents(CREDITS_FILE), true) ?? [];
}
function saveCredits(array $credits): void {
    file_put_contents(CREDITS_FILE, json_encode($credits, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
function deductCost(string $building, int $inputTokens, int $outputTokens): void {
    $cost    = ($inputTokens * 0.0000008) + ($outputTokens * 0.000004);
    $credits = loadCredits();
    if (!isset($credits[$building])) {
        $credits[$building] = ['allocated' => CREDITS_DEFAULT_ALLOCATED, 'used' => 0];
    }
    $credits[$building]['used'] = round(($credits[$building]['used'] ?? 0) + $cost, 6);
    saveCredits($credits);
}

function logAdminUsage(string $building): void {
    $file  = __DIR__ . '/faqs/woolsy_admin_usage.json';
    $month = date('Y-m');
    $data  = file_exists($file) ? (json_decode(file_get_contents($file), true) ?? []) : [];
    $data[$building][$month] = ($data[$building][$month] ?? 0) + 1;
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Load admin manual text (strip CSS, images, and HTML tags)
function loadAdminManual(): string {
    $file = __DIR__ . '/docs/Sheepsite-Admin-Manual.html';
    if (!file_exists($file)) return '';
    $html = file_get_contents($file);
    $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);
    $html = preg_replace('/<img[^>]*>/i', '', $html);
    $text = strip_tags($html);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

// Credit check
$credits        = loadCredits();
$buildingCredit = $credits[$building] ?? ['allocated' => CREDITS_DEFAULT_ALLOCATED, 'used' => 0];
$allocated      = (float)($buildingCredit['allocated'] ?? CREDITS_DEFAULT_ALLOCATED);
$used           = (float)($buildingCredit['used']      ?? 0);
if ($used >= $allocated) {
    echo json_encode(['error' => 'Woolsy credits exhausted for this building. Top up via the Woolsy management page.']);
    exit;
}

$manualText = loadAdminManual();

$systemPrompt = <<<PROMPT
You are Woolsy, the friendly assistant for SheepSite platform administrators.

Your job is to help building admins use the SheepSite admin panel — managing residents, user accounts, files, billing, Woolsy credits, reports, and settings.

Answer based on the SheepSite Admin Manual content provided below. Be concise and direct — admins are busy. For simple how-to questions answer in 2–4 sentences. For multi-step tasks use a short numbered list. Avoid restating the question.

Guidelines:
- Give specific steps when asked how to do something.
- If a feature doesn't exist or isn't covered in the manual, say so clearly rather than guessing.
- Never invent features or options that aren't in the manual.
- When relevant, mention which page or card in the admin panel the admin should navigate to.
- If the question is unrelated to administering this SheepSite platform, reply only with: "I can only answer questions about administering this site in this context."

---
SHEEPSITE ADMIN MANUAL:
{$manualText}
PROMPT;

// Build messages
$messages = [];
foreach ($history as $turn) {
    $role = $turn['role'] ?? '';
    if (in_array($role, ['user', 'assistant'])) {
        $messages[] = ['role' => $role, 'content' => $turn['content']];
    }
}
$messages[] = ['role' => 'user', 'content' => $question];

$apiKey = getenv('ANTHROPIC_API_KEY');

$payload = json_encode([
    'model'      => 'claude-haiku-4-5-20251001',
    'max_tokens' => 512,
    'system'     => [
        [
            'type'          => 'text',
            'text'          => $systemPrompt,
            'cache_control' => ['type' => 'ephemeral'],
        ],
    ],
    'messages'   => $messages,
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
        'anthropic-beta: prompt-caching-2024-07-31',
    ],
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    echo json_encode(['error' => 'Request failed. Please try again.']);
    exit;
}

$data   = json_decode($response, true);
$answer = '';
foreach ($data['content'] ?? [] as $block) {
    if ($block['type'] === 'text') { $answer = $block['text']; break; }
}

if (!$answer) {
    echo json_encode(['error' => 'No response received.']);
    exit;
}

if (isset($data['usage']['input_tokens'], $data['usage']['output_tokens'])) {
    deductCost($building, (int)$data['usage']['input_tokens'], (int)$data['usage']['output_tokens']);
    require_once __DIR__ . '/billing-helpers.php';
    $freshCr  = loadCredits();
    $freshBld = $freshCr[$building] ?? [];
    checkWoolsyThreshold(
        $building,
        (float)($freshBld['used']      ?? 0),
        (float)($freshBld['allocated'] ?? CREDITS_DEFAULT_ALLOCATED)
    );
}
logAdminUsage($building);
echo json_encode(['answer' => $answer]);
