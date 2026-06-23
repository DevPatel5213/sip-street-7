<?php
/**
 * Sip Street 7 — newsletter signup → saves emails to a CSV.
 *
 * No emails are sent. Every signup is appended to  private/subscribers.csv.
 *
 * 👉 To get your list:
 *    Hostinger File Manager → public_html → private → download subscribers.csv
 *    (open it in Excel / Google Sheets). The "private" folder is blocked from
 *    public web access, so nobody can steal your subscriber list.
 */

// --- Where the list is stored (auto-created, web-protected) ---
$dir = __DIR__ . '/private';
if (!is_dir($dir)) {
  @mkdir($dir, 0755, true);
  @file_put_contents($dir . '/.htaccess', "Require all denied\n");      // Apache/LiteSpeed
  @file_put_contents($dir . '/index.html', "Nothing to see here.\n");   // no directory listing
}
$CSV = $dir . '/subscribers.csv';

$wantsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

function respond($ok, $error = '', $wantsJson = true) {
  if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    if (!$ok) http_response_code(422);
    echo json_encode(['ok' => $ok, 'error' => $error]);
  } else {
    header('Location: /index.html?' . ($ok ? 'subscribed=1' : 'subscribed=0') . '#newsletter');
  }
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  respond(false, 'Method not allowed', $wantsJson);
}

// Honeypot — bots fill the hidden "website" field
if (!empty($_POST['website'])) {
  respond(true, '', $wantsJson);
}

$email = trim($_POST['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
  respond(false, 'Please enter a valid email address.', $wantsJson);
}

// Skip duplicates — if already on the list, still show success
$emailLower = strtolower($email);
if (is_readable($CSV) && ($r = fopen($CSV, 'r'))) {
  while (($row = fgetcsv($r)) !== false) {
    if (isset($row[0]) && strtolower(trim($row[0])) === $emailLower) {
      fclose($r);
      respond(true, '', $wantsJson);
    }
  }
  fclose($r);
}

// Append the new subscriber (with a header row the first time)
$isNew = !file_exists($CSV);
$fh = @fopen($CSV, 'a');
if ($fh === false) {
  respond(false, 'Could not save right now - please try again.', $wantsJson);
}
if (flock($fh, LOCK_EX)) {
  if ($isNew) {
    fputcsv($fh, ['email', 'date', 'ip']);
  }
  fputcsv($fh, [$email, date('Y-m-d H:i:s'), $_SERVER['REMOTE_ADDR'] ?? '']);
  flock($fh, LOCK_UN);
  fclose($fh);
  respond(true, '', $wantsJson);
}
fclose($fh);
respond(false, 'Could not save right now - please try again.', $wantsJson);
