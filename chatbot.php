<?php
// chatbot.php — FAQ chatbot API endpoint
//
// POST JSON: {"building": "LyndhurstH", "question": "...", "history": [...]}
// Returns:   {"answer": "..."}
//
// Context layers loaded (if files exist):
//   faqs/_global.txt                  — universal condo/HOA concepts
//   faqs/states/{STATE}.txt           — state-specific law (e.g. FL.txt)
//   faqs/communities/{COMMUNITY}.txt  — master community rules (e.g. CVE.txt)
//   faqs/{building}.txt               — building-specific FAQ
//   faqs/{building}_rules.md          — distilled governing docs (from setup phase)

header('Content-Type: application/json');

$buildings = require __DIR__ . '/buildings.php';

// --- Credit system ---

define('CREDITS_FILE', __DIR__ . '/faqs/woolsy_credits.json');
define('CREDITS_DEFAULT_ALLOCATED', 1.0);

function loadCredits(): array {
    if (!file_exists(CREDITS_FILE)) return [];
    return json_decode(file_get_contents(CREDITS_FILE), true) ?? [];
}

function saveCredits(array $credits): void {
    file_put_contents(CREDITS_FILE, json_encode($credits, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function deductCost(string $building, int $inputTokens, int $outputTokens): void {
    // Haiku pricing: $0.80/MTok input, $4.00/MTok output → 1 credit = $1
    $cost = ($inputTokens * 0.0000008) + ($outputTokens * 0.000004);
    $credits = loadCredits();
    if (!isset($credits[$building])) {
        $credits[$building] = ['allocated' => CREDITS_DEFAULT_ALLOCATED, 'used' => 0];
    }
    $credits[$building]['used'] = round(($credits[$building]['used'] ?? 0) + $cost, 6);
    saveCredits($credits);
}

$body     = json_decode(file_get_contents('php://input'), true);
$building = $body['building'] ?? '';
$question = trim($body['question'] ?? '');
$history  = $body['history'] ?? [];

if (!$question || !isset($buildings[$building])) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// --- Credit check ---
$credits        = loadCredits();
$buildingCredit = $credits[$building] ?? ['allocated' => CREDITS_DEFAULT_ALLOCATED, 'used' => 0];
$allocated      = (float)($buildingCredit['allocated'] ?? CREDITS_DEFAULT_ALLOCATED);
$used           = (float)($buildingCredit['used']      ?? 0);

if ($used >= $allocated) {
    echo json_encode(['error' => 'Woolsy is temporarily unavailable. Please contact your building administrator.']);
    exit;
}

// --- Assemble FAQ context ---

$state     = $buildings[$building]['state']     ?? 'FL';
$community = $buildings[$building]['community'] ?? '';

function loadFaq(string $path): string {
    return file_exists($path) ? "\n\n" . trim(file_get_contents($path)) : '';
}

// Always load: global, community (if any), building-specific layers
$context  = loadFaq(__DIR__ . '/faqs/_global.txt');
if ($community) {
    $context .= loadFaq(__DIR__ . "/faqs/communities/{$community}.txt");
}
$context .= loadFaq(__DIR__ . "/faqs/{$building}.txt");
$context .= loadFaq(__DIR__ . "/faqs/{$building}_rules.md");

// Document index — file listing of all public documents on the site
$docIndexFile = __DIR__ . "/faqs/{$building}_docindex.txt";
$docIndex = file_exists($docIndexFile)
    ? "\n\n---\nDOCUMENT INDEX — files available on the building website:\n" . trim(file_get_contents($docIndexFile))
    : '';

// Selectively load state law layer — only when question is about legal rights,
// meetings, records, statutes, or association governance
$stateKeywords = '/\b(record|meeting|vote|elect|fine|right|statute|law|718|website|reserve|inspection|estoppel|petition|suspend|quorum|notice|proxy|amendment|budget|assessment|arbitration|hearing|committee|sirs|milestone)\b/i';
if ($state && preg_match($stateKeywords, $question)) {
    $context .= loadFaq(__DIR__ . "/faqs/states/{$state}.txt");
}

$systemPrompt = <<<PROMPT
You are Woolsy, a friendly and knowledgeable assistant for {$building} condominium association residents.

Answer questions based on the FAQ and governing document information provided below.
Be concise and conversational — this is a chat interface, not a document. For simple questions,
answer in 2-4 sentences. For multi-part questions, use brief bullets. Avoid headers and avoid
restating the question.

Guidelines:
- Use the provided content to answer as fully as possible — don't deflect unnecessarily.
- If the answer is partially covered, give what you know and note what's unclear.
- Never guess about specific dollar amounts, deadlines, or legal interpretations not in the content.
- The rules content below includes source markers in the format "(Source: ...)". When answering,
  use that source naturally in your response (e.g. "According to the Declaration of Condominium,
  smoking is prohibited..." or "The Board's Welcome Guide states..."). Always include the source.
- If the question is fully answered by the rules, do NOT mention contacting the board at all.
  A complete answer stands on its own.
- Only mention contacting the board when the question specifically requires a board decision
  or approval that cannot be answered from the rules (e.g. requesting an exception, submitting
  an application, filing a complaint). Never use it as a polite closing or catch-all.
- A document index is provided below the FAQ content listing all files available on the website.
  When relevant, tell the resident where to find a specific document or form (e.g. "The Renovation
  Request Form is in the Forms section of the Public Resources"). If you're not sure which document
  applies, suggest using the site's Search feature.

---
{$context}{$docIndex}
PROMPT;

// --- Build messages array (include conversation history) ---

$messages = [];
foreach ($history as $turn) {
    $role = $turn['role'] ?? '';
    if (in_array($role, ['user', 'assistant'])) {
        $messages[] = ['role' => $role, 'content' => $turn['content']];
    }
}
$messages[] = ['role' => 'user', 'content' => $question];

// --- Call Claude API ---

$apiKey = getenv('ANTHROPIC_API_KEY');

$payload = json_encode([
    'model'      => 'claude-haiku-4-5-20251001',
    'max_tokens' => 768,
    'system'     => $systemPrompt,
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
    ],
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    echo json_encode(['error' => 'Request failed']);
    exit;
}

$data   = json_decode($response, true);
$answer = '';

foreach ($data['content'] ?? [] as $block) {
    if ($block['type'] === 'text') {
        $answer = $block['text'];
        break;
    }
}

if (!$answer) {
    echo json_encode(['error' => 'No response from assistant']);
    exit;
}

// Deduct credit cost using token counts from API response
if (isset($data['usage']['input_tokens'], $data['usage']['output_tokens'])) {
    deductCost($building, (int)$data['usage']['input_tokens'], (int)$data['usage']['output_tokens']);
}

echo json_encode(['answer' => $answer]);
