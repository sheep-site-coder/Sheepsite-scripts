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

$body     = json_decode(file_get_contents('php://input'), true);
$building = $body['building'] ?? '';
$question = trim($body['question'] ?? '');
$history  = $body['history'] ?? [];

if (!$question || !isset($buildings[$building])) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// --- Assemble FAQ context ---

$state     = $buildings[$building]['state']     ?? 'FL';
$community = $buildings[$building]['community'] ?? '';

function loadFaq(string $path): string {
    return file_exists($path) ? "\n\n" . trim(file_get_contents($path)) : '';
}

$context  = loadFaq(__DIR__ . '/faqs/_global.txt');
$context .= loadFaq(__DIR__ . "/faqs/states/{$state}.txt");
if ($community) {
    $context .= loadFaq(__DIR__ . "/faqs/communities/{$community}.txt");
}
$context .= loadFaq(__DIR__ . "/faqs/{$building}.txt");
$context .= loadFaq(__DIR__ . "/faqs/{$building}_rules.md");

$systemPrompt = <<<PROMPT
You are Woolsy, a friendly and knowledgeable assistant for {$building} condominium association residents.

Answer questions based on the FAQ and governing document information provided below.
Be concise and friendly.

Guidelines:
- Use the provided content to answer as fully as possible — don't deflect unnecessarily.
- If the answer is partially covered, give what you know and note what's unclear.
- Never guess about specific dollar amounts, deadlines, or legal interpretations not in the content.
- Only suggest contacting the board when the question genuinely cannot be answered from the
  provided content and truly requires board discretion or a decision (e.g. approval requests,
  complaints, exceptions to rules). Do NOT suggest contacting the board just because a topic
  isn't covered in detail — simply say you don't have that information.
- Never say "contact the board" more than once per response.

---
{$context}
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
    'max_tokens' => 512,
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

echo json_encode(['answer' => $answer]);
