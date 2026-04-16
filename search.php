<?php
// -------------------------------------------------------
// search.php
// Owner-facing file search across Public and Private Drive trees.
// Also searches tag index (tags/{building}.json).
//
//   https://sheepsite.com/Scripts/search.php?building=LyndhurstH
//
// Requires owner login — reuses private_auth_{building} session.
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR',   __DIR__ . '/credentials/');
define('TAGS_DIR',          __DIR__ . '/tags/');
require_once __DIR__ . '/login-stats.php';
require_once __DIR__ . '/storage/r2-storage.php';

$buildings = require __DIR__ . '/buildings.php';

// -------------------------------------------------------
// Validate building
// -------------------------------------------------------
$building = $_GET['building'] ?? '';

if (!$building || !array_key_exists($building, $buildings)) {
  die('<p style="color:red;">Invalid or missing building name.</p>');
}

$config     = $buildings[$building];
$buildLabel = ucwords(str_replace(['_', '-'], ' ', $building));
require __DIR__ . '/suspension.php';
$returnURL  = $_GET['return'] ?? '';
if ($returnURL && !preg_match('/^https?:\/\//', $returnURL)) $returnURL = '';

$sessionKey = 'private_auth_' . $building;
$baseURL    = '?building=' . urlencode($building) . ($returnURL ? '&return=' . urlencode($returnURL) : '');

// -------------------------------------------------------
// Logout
// -------------------------------------------------------
if (isset($_GET['logout'])) {
  unset($_SESSION[$sessionKey]);
  header('Location: ' . $baseURL);
  exit;
}

// -------------------------------------------------------
// Login — handle POST
// -------------------------------------------------------
$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password']       ?? '';

  $credFile = CREDENTIALS_DIR . $building . '.json';
  $users    = file_exists($credFile) ? json_decode(file_get_contents($credFile), true) : [];

  $authenticated = false;
  foreach ($users as $u) {
    if ($u['user'] === $username && password_verify($password, $u['pass'])) {
      $authenticated = true;
      break;
    }
  }

  if ($authenticated) {
    $_SESSION[$sessionKey] = $username;
    logLogin($building, $username);
    $mustChange = false;
    foreach ($users as $u) {
      if ($u['user'] === $username && !empty($u['mustChange'])) {
        $mustChange = true;
        break;
      }
    }
    if ($mustChange) {
      $searchRedirect = 'search.php?building=' . urlencode($building)
                      . ($returnURL ? '&return=' . urlencode($returnURL) : '');
      header('Location: change-password.php?building=' . urlencode($building)
           . '&mustchange=1'
           . '&redirect=' . urlencode($searchRedirect)
           . ($returnURL ? '&return=' . urlencode($returnURL) : ''));
    } else {
      header('Location: ' . $baseURL);
    }
    exit;
  } else {
    $loginError = 'Invalid username or password.';
  }
}

// -------------------------------------------------------
// Admin bypass — only when ?adminview=1 is explicitly set
// -------------------------------------------------------
$adminSessionKey = 'manage_auth_' . $building;
$isAdminViewing  = !empty($_SESSION[$adminSessionKey]) && !empty($_GET['adminview']);

// -------------------------------------------------------
// Login — show form if not authenticated
// -------------------------------------------------------
if (!$isAdminViewing && empty($_SESSION[$sessionKey])) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($buildLabel) ?> – Search</title>
  <style>
    body       { font-family: sans-serif; max-width: 400px; margin: 4rem auto; padding: 0 1rem; }
    h1         { margin-bottom: 0.25rem; font-size: 1.4rem; }
    .subtitle  { color: #666; font-size: 0.9rem; margin-bottom: 2rem; }
    label      { display: block; font-size: 0.9rem; font-weight: bold; margin-bottom: 0.25rem; }
    input[type=text], input[type=password] {
                 width: 100%; box-sizing: border-box; padding: 0.5rem 0.6rem;
                 border: 1px solid #ccc; border-radius: 4px; font-size: 1rem;
                 margin-bottom: 1rem; }
    .login-btn { width: 100%; padding: 0.6rem; background: #0070f3; color: #fff;
                 border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; }
    .login-btn:hover { background: #005bb5; }
    .error     { color: red; font-size: 0.9rem; margin-bottom: 1rem; }
    .back-btn  { display: inline-block; margin-bottom: 1.5rem; font-size: 0.9rem;
                 color: #0070f3; text-decoration: none; }
    .back-btn:hover { text-decoration: underline; }
    .admin-bypass { background:#f0f7ff; border:1px solid #b3d4f5; border-radius:6px;
                    padding:0.75rem 1rem; margin-bottom:1.5rem; font-size:0.9rem; }
    .admin-bypass a { color:#0070f3; font-weight:bold; text-decoration:none; }
    .admin-bypass a:hover { text-decoration:underline; }
  </style>
</head>
<body>
  <?php if ($returnURL): ?>
    <a href="<?= htmlspecialchars($returnURL) ?>" class="back-btn">← Back to site</a>
  <?php endif; ?>
  <h1><?= htmlspecialchars($buildLabel) ?></h1>
  <div class="subtitle">File search — login required</div>

  <?php if (!empty($_SESSION[$adminSessionKey])): ?>
    <div class="admin-bypass">
      Logged in as admin &mdash;
      <a href="<?= htmlspecialchars($baseURL . '&adminview=1') ?>">Continue as Admin →</a>
    </div>
  <?php endif; ?>

  <?php if ($loginError): ?>
    <p class="error"><?= htmlspecialchars($loginError) ?></p>
  <?php endif; ?>

  <form method="post" action="<?= htmlspecialchars($baseURL) ?>">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" autocomplete="username" autocapitalize="none" autofocus>

    <label for="password">Password</label>
    <input type="password" id="password" name="password" autocomplete="current-password">

    <button type="submit" class="login-btn">Log in</button>
  </form>
  <p style="text-align:center;margin-top:1rem;font-size:0.85rem;">
    <a href="forgot-password.php?building=<?= urlencode($building) ?>" style="color:#0070f3;text-decoration:none;">Forgot password?</a>
  </p>
</body>
</html>
<?php
  exit;
}

// -------------------------------------------------------
// mustChange check
// -------------------------------------------------------
if (!$isAdminViewing) {
  $credFile = CREDENTIALS_DIR . $building . '.json';
  $allUsers = file_exists($credFile) ? json_decode(file_get_contents($credFile), true) : [];
  foreach ($allUsers as $u) {
    if ($u['user'] === $_SESSION[$sessionKey] && !empty($u['mustChange'])) {
      $searchRedirect = 'search.php?building=' . urlencode($building)
                      . ($returnURL ? '&return=' . urlencode($returnURL) : '');
      header('Location: change-password.php?building=' . urlencode($building)
           . '&mustchange=1'
           . '&redirect=' . urlencode($searchRedirect)
           . ($returnURL ? '&return=' . urlencode($returnURL) : ''));
      exit;
    }
  }
}

// -------------------------------------------------------
// JSON search — filename scan via R2 listing + tag index
// -------------------------------------------------------
if (isset($_GET['json']) && $_GET['json'] === 'search') {
  header('Content-Type: application/json');

  $query = trim($_GET['q'] ?? '');
  if (!$query) {
    echo json_encode(['results' => []]);
    exit;
  }

  $r2cfg = _r2Cfg();
  $seen  = [];

  // --- Filename search: flat R2 listing scan for both trees ---
  $nameResults = [];
  foreach (['public', 'private'] as $tree) {
    $prefix = $building . '/' . $tree . '/';
    $token  = null;
    do {
      $q2 = ['list-type' => '2', 'prefix' => $prefix, 'max-keys' => '1000'];
      if ($token) $q2['continuation-token'] = $token;
      [$status, $body] = _r2Request('GET', '/' . $r2cfg['bucket'], $q2);
      if ($status !== 200) break;
      $sx = @simplexml_load_string($body);
      if (!$sx) break;
      foreach ($sx->Contents as $obj) {
        $key  = (string)$obj->Key;
        $size = (int)(string)$obj->Size;
        $name = basename($key);
        if ($name === '.keep' || str_ends_with($key, '/')) continue;
        if (stripos($name, $query) !== false) {
          $nameResults[] = ['id' => $key, 'name' => $name, 'size' => _r2FmtSize($size), 'tree' => $tree, 'via' => 'name'];
          $seen[$key]    = true;
        }
      }
      $token = (string)($sx->NextContinuationToken ?? '');
    } while ($token !== '');
  }

  // --- Tag search ---
  $tagsFile = TAGS_DIR . $building . '.json';
  $allTags  = file_exists($tagsFile) ? json_decode(file_get_contents($tagsFile), true) : [];
  if (!is_array($allTags)) $allTags = [];

  $words = preg_split('/\s+/', strtolower($query), -1, PREG_SPLIT_NO_EMPTY);

  $tagResults = [];
  foreach ($allTags as $fileId => $entry) {
    $fileTags = is_array($entry) && isset($entry['tags']) ? $entry['tags'] : (is_array($entry) ? $entry : []);
    $fileName = is_array($entry) && isset($entry['name']) ? $entry['name'] : '';
    $tree     = is_array($entry) && isset($entry['tree']) ? $entry['tree'] : 'private';

    if (empty($fileTags) || isset($seen[$fileId])) continue;

    // AND logic: every query word must appear in at least one tag (substring)
    $allWordsMatch = true;
    foreach ($words as $word) {
      $wordFound = false;
      foreach ($fileTags as $tag) {
        if (strpos(strtolower($tag), $word) !== false) { $wordFound = true; break; }
      }
      if (!$wordFound) { $allWordsMatch = false; break; }
    }

    if ($allWordsMatch) {
      $tagResults[] = ['id' => $fileId, 'name' => $fileName ?: '(unnamed)', 'size' => '', 'tree' => $tree, 'via' => 'tag', 'tags' => $fileTags];
      $seen[$fileId] = true;
    }
  }

  // Annotate filename results with their tags
  foreach ($nameResults as &$r) {
    if (!empty($allTags[$r['id']])) {
      $entry = $allTags[$r['id']];
      $r['tags'] = isset($entry['tags']) ? $entry['tags'] : (is_array($entry) ? $entry : []);
    }
  }
  unset($r);

  usort($tagResults, fn($a, $b) => strcmp($a['name'], $b['name']));
  echo json_encode(['results' => array_merge($nameResults, array_values($tagResults))]);
  exit;
}

$currentUser = $isAdminViewing
    ? ($_SESSION[$adminSessionKey]['user'] ?? 'admin')
    : $_SESSION[$sessionKey];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($buildLabel) ?> – File Search</title>
  <style>
    body           { font-family: sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
    .top-bar       { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    h1             { margin: 0; font-size: 1.4rem; }
    .user-actions  { font-size: 0.85rem; }
    .user-actions a { color: #0070f3; text-decoration: none; margin-left: 0.75rem; }
    .user-actions a:hover { text-decoration: underline; }

    /* Search bar */
    .search-bar    { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; }
    .search-input  { flex: 1; padding: 0.6rem 0.8rem; border: 1px solid #ccc; border-radius: 6px;
                     font-size: 1rem; }
    .search-btn    { padding: 0.6rem 1.2rem; background: #0070f3; color: #fff; border: none;
                     border-radius: 6px; font-size: 1rem; cursor: pointer; white-space: nowrap; }
    .search-btn:hover { background: #005bb5; }

    /* Results */
    .result-count  { font-size: 0.85rem; color: #888; margin-bottom: 1rem; }
    .file-card     { display: flex; align-items: flex-start; gap: 1rem; padding: 0.75rem;
                     border: 1px solid #ddd; border-radius: 6px; margin-bottom: 0.4rem; }
    .file-icon     { font-size: 1.2rem; flex-shrink: 0; margin-top: 0.1rem; }
    .file-body     { flex: 1; min-width: 0; }
    .file-name     { font-weight: bold; color: #333; text-decoration: none; display: block;
                     white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .file-name:hover { color: #0070f3; text-decoration: underline; }
    .file-meta     { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.3rem;
                     flex-wrap: wrap; }
    .tree-badge    { font-size: 0.75rem; padding: 0.15rem 0.5rem; border-radius: 10px;
                     font-weight: 600; white-space: nowrap; }
    .tree-badge.public  { background: #e6f4ea; color: #1a7f37; }
    .tree-badge.private { background: #fff3cd; color: #856404; }
    .file-size     { font-size: 0.8rem; color: #888; }
    .tag-chip      { font-size: 0.72rem; padding: 0.1rem 0.45rem; border-radius: 10px;
                     background: #e8f0fe; color: #1a56db; white-space: nowrap; }
    .download-btn  { flex-shrink: 0; padding: 0.4rem 0.9rem; background: #0070f3; color: #fff;
                     text-decoration: none; border-radius: 4px; font-size: 0.85rem; align-self: center; }
    .download-btn:hover { background: #005bb5; }

    /* States */
    .hint          { color: #888; font-size: 0.95rem; padding: 2rem 0; text-align: center; }
    .loading       { color: #888; font-style: italic; padding: 2rem 0; }
    .error         { color: #c00; padding: 1rem 0; }
    .spinner       { display: inline-block; width: 14px; height: 14px; border: 2px solid #ddd;
                     border-top-color: #0070f3; border-radius: 50%;
                     animation: spin 0.7s linear infinite; vertical-align: middle; margin-right: 0.4rem; }
    @keyframes spin { to { transform: rotate(360deg); } }

    .back-btn      { display: inline-block; margin-bottom: 1rem; font-size: 0.9rem;
                     color: #0070f3; text-decoration: none; }
    .back-btn:hover { text-decoration: underline; }
  </style>
</head>
<body>

  <?php if ($returnURL): ?>
    <a href="<?= htmlspecialchars($returnURL) ?>" class="back-btn">← Back to site</a>
  <?php endif; ?>

  <div class="top-bar">
    <h1><?= htmlspecialchars($buildLabel) ?> – File Search</h1>
    <div>
      <span class="user-actions">
        <a href="display-private-dir.php?building=<?= urlencode($building) ?><?= $returnURL ? '&return=' . urlencode($returnURL) : '' ?>">Browse files</a>
        <a href="change-password.php?building=<?= urlencode($building) ?><?= $returnURL ? '&return=' . urlencode($returnURL) : '' ?>">Change password</a>
        <a href="<?= htmlspecialchars($baseURL) ?>&logout=1">Log out</a>
      </span>
    </div>
  </div>

  <form class="search-bar" id="search-form">
    <input type="text" class="search-input" id="search-input"
           placeholder="Search files by name or tag…" autocomplete="off" autofocus>
    <button type="submit" class="search-btn">Search</button>
  </form>

  <p style="font-size:0.9rem;color:#555;margin-bottom:1.25rem;">
    Search for documents by file name or keyword. Results include both public documents
    and private resident-only accessible files. Files may be previewed or downloaded
    directly from this page.
  </p>

  <div id="results"><p class="hint">Enter a search term above.</p></div>

  <script>
  (function () {
    var building  = <?= json_encode($building) ?>;
    var returnURL = <?= json_encode($returnURL) ?>;
    var base      = 'search.php?building=' + encodeURIComponent(building)
                  + (returnURL ? '&return=' + encodeURIComponent(returnURL) : '');
    var currentXHR = null;

    document.getElementById('search-form').addEventListener('submit', function (e) {
      e.preventDefault();
      var q = document.getElementById('search-input').value.trim();
      if (!q) return;
      runSearch(q);
    });

    function runSearch(q) {
      var resultsEl = document.getElementById('results');
      resultsEl.innerHTML = '<p class="loading"><span class="spinner"></span>Searching\u2026</p>';

      if (currentXHR) currentXHR.abort && currentXHR.abort();

      var url = base + '&json=search&q=' + encodeURIComponent(q);

      fetch(url)
        .then(function (r) { return r.json(); })
        .then(function (d) { renderResults(d.results || [], q); })
        .catch(function () {
          resultsEl.innerHTML = '<p class="error">Search failed — please try again.</p>';
        });
    }

    function renderResults(results, query) {
      var resultsEl = document.getElementById('results');

      if (!results.length) {
        resultsEl.innerHTML = '<p class="hint">No files found for <strong>' + esc(query) + '</strong>.</p>';
        return;
      }

      var html = '<div class="result-count">' + results.length + ' result' + (results.length !== 1 ? 's' : '') + '</div>';

      results.forEach(function (f) {
        var isPrivate  = f.tree === 'private';
        var badgeClass = isPrivate ? 'private' : 'public';
        var badgeLabel = isPrivate ? 'Private' : 'Public';

        // Build file link/download
        var fileURL, downloadURL;
        if (isPrivate) {
          var privateBase = 'display-private-dir.php?building=' + encodeURIComponent(building)
                          + (returnURL ? '&return=' + encodeURIComponent(returnURL) : '');
          fileURL     = privateBase + '&fileId=' + encodeURIComponent(f.id) + '&inline=1';
          downloadURL = privateBase + '&fileId=' + encodeURIComponent(f.id);
        } else {
          var publicBase = 'display-public-dir.php?building=' + encodeURIComponent(building)
                         + (returnURL ? '&return=' + encodeURIComponent(returnURL) : '');
          fileURL     = publicBase + '&file=' + encodeURIComponent(f.id);
          downloadURL = publicBase + '&file=' + encodeURIComponent(f.id);
        }

        // Tags
        var tagHtml = '';
        if (f.tags && f.tags.length) {
          tagHtml = f.tags.map(function (t) {
            return '<span class="tag-chip">' + esc(t) + '</span>';
          }).join('');
        }

        html += '<div class="file-card">'
              +   '<span class="file-icon">&#128196;</span>'
              +   '<div class="file-body">'
              +     '<a href="' + esc(fileURL) + '" target="_blank" class="file-name">' + esc(f.name) + '</a>'
              +     '<div class="file-meta">'
              +       '<span class="tree-badge ' + badgeClass + '">' + badgeLabel + '</span>'
              +       (f.size ? '<span class="file-size">' + esc(f.size) + '</span>' : '')
              +       tagHtml
              +     '</div>'
              +   '</div>'
              +   '<a href="' + esc(downloadURL) + '" class="download-btn">Download</a>'
              + '</div>';
      });

      resultsEl.innerHTML = html;
    }

    function esc(s) {
      return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }
  })();
  </script>
</body>
</html>
