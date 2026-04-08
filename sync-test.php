<?php
// sync-test.php — button POST diagnostic
// Visit: https://qgscratch.website/Scripts/sync-test.php
// DELETE after testing.

$received = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $received = $_POST;
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Button POST Test</title>
  <style>
    body { font-family: monospace; max-width: 700px; margin: 2rem auto; padding: 0 1rem; }
    .box { border: 1px solid #ccc; border-radius: 6px; padding: 1rem 1.2rem; margin-bottom: 1.5rem; }
    .ok  { background: #e6f4ea; border-color: #6abf69; }
    .err { background: #ffeef0; border-color: #c00; }
    pre  { background: #f5f5f5; padding: 0.75rem; border-radius: 4px; font-size: 0.9rem; }
    button { padding: 0.4rem 0.9rem; background: #c00; color: #fff; border: none;
             border-radius: 4px; font-size: 0.9rem; cursor: pointer; margin-right: 0.5rem; }
    h2 { margin: 0 0 0.5rem; font-size: 1rem; }
    p  { margin: 0 0 0.75rem; font-size: 0.9rem; color: #555; }
    hr { border: none; border-top: 1px solid #eee; margin: 2rem 0; }
  </style>
</head>
<body>
<h1>Button POST Test</h1>

<?php if ($received !== null): ?>
<div class="box <?= isset($received['action']) ? 'ok' : 'err' ?>">
  <h2><?= isset($received['action']) ? '✓ action received: ' . htmlspecialchars($received['action']) : '✗ action key MISSING from POST' ?></h2>
  <pre><?= htmlspecialchars(print_r($received, true)) ?></pre>
</div>
<?php endif; ?>

<hr>
<h2>Test A — onclick disables button (old pattern)</h2>
<form method="post">
  <input type="hidden" name="remove_list[]" value="user1">
  <button type="submit" name="action" value="test_a"
          onclick="this.disabled=true;this.textContent='Removing…'">Remove checked</button>
</form>

<hr>
<h2>Test B — onclick disables button + hidden input</h2>
<form method="post">
  <input type="hidden" name="remove_list[]" value="user1">
  <input type="hidden" name="action" value="test_b">
  <button type="submit"
          onclick="this.disabled=true;this.textContent='Removing…'">Remove checked</button>
</form>

<hr>
<h2>Test C — no onclick (control)</h2>
<form method="post">
  <input type="hidden" name="remove_list[]" value="user1">
  <button type="submit" name="action" value="test_c">Remove checked</button>
</form>

<hr>
<h2>Test D — onsubmit on the form (proposed fix)</h2>
<p>Disables button via the form's onsubmit instead of button onclick.</p>
<form method="post" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Removing…'">
  <input type="hidden" name="remove_list[]" value="user1">
  <input type="hidden" name="action" value="test_d">
  <button type="submit">Remove checked</button>
</form>

</body>
</html>
