<?php
/**
 * Sip Street 7 — newsletter signup handler (authenticated SMTP).
 *
 * On submit it sends TWO emails through your Hostinger mailbox:
 *   1. a "Welcome to the street" confirmation TO the subscriber
 *   2. a notification TO the shop inbox (hello@)
 *
 * Hostinger silently drops PHP mail(), so this uses SMTP. Self-contained.
 *
 * SETUP (on the server, NOT in the public repo):
 *   Create  secrets.php  next to this file with one line:
 *     <?php $SMTP_PASS = 'your-hello@-mailbox-password';
 */

// ===== CONFIG =====
$TO        = 'hello@sipstreet7.com.au';   // shop inbox (gets notified)
$SMTP_USER = 'hello@sipstreet7.com.au';   // mailbox login + From address
$SMTP_HOST = 'smtp.hostinger.com';        // Hostinger outgoing server
$SMTP_PORT = 465;                         // 465 = SSL. If blocked, try 587 (STARTTLS)
$BRAND     = 'Sip Street 7';

$SMTP_PASS = '';
@include __DIR__ . '/secrets.php';        // sets $SMTP_PASS (gitignored)
// ==================

$wantsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

function respond($ok, $error = '', $wantsJson = true) {
  if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    if (!$ok) http_response_code($error === 'Method not allowed' ? 405 : 422);
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

if ($SMTP_PASS === '') {
  respond(false, 'Email is not configured yet.', $wantsJson);
}

// ---------- 1) Welcome email TO the subscriber (HTML) ----------
$welcomeSubject = 'Welcome to the street 🥤 - ' . $BRAND;
$welcomeHtml =
  '<div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:auto;background:#fbf4e6;border-radius:16px;padding:30px;color:#15281b">'
  . '<h1 style="color:#1a7a3e;margin:0 0 10px;font-size:24px">Welcome to the street! 🥤</h1>'
  . '<p style="margin:0 0 14px;font-size:15px;line-height:1.6">Thanks for joining the <strong>Sip Street 7</strong> list — you\'re officially one of us.</p>'
  . '<p style="margin:0 0 14px;font-size:15px;line-height:1.6">You\'ll be first to hear about new blends, seasonal specials and the odd free treat. No spam — pinky promise.</p>'
  . '<p style="margin:0;font-size:16px;line-height:1.6;color:#1a7a3e;font-weight:bold">The treat starts here.</p>'
  . '<p style="margin:20px 0 0;font-size:12px;color:#5d6f60">Sip Street 7 · 1 Marion St, Midland WA 6056 · sipstreet7.com.au</p>'
  . '</div>';

$errW = '';
$welcomeOk = smtp_send($SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS,
  $SMTP_USER, $BRAND, $email, $SMTP_USER, $welcomeSubject, $welcomeHtml, $errW, true);

// ---------- 2) Notification TO the shop inbox (plain text) ----------
$notifySubject = 'New newsletter signup - ' . $BRAND;
$notifyBody  = "A new subscriber joined the Sip Street list:\r\n\r\n";
$notifyBody .= "Email: $email\r\n";
$notifyBody .= "When:  " . date('Y-m-d H:i:s') . "\r\n";
$notifyBody .= "IP:    " . ($_SERVER['REMOTE_ADDR'] ?? 'n/a') . "\r\n";

$errN = '';
smtp_send($SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS,
  $SMTP_USER, $BRAND, $TO, $email, $notifySubject, $notifyBody, $errN, false);

// Success is driven by the subscriber's welcome email (the one they expect)
if ($welcomeOk) {
  respond(true, '', $wantsJson);
} else {
  error_log('[subscribe.php] welcome err: ' . $errW . ' | notify err: ' . $errN);
  respond(false, 'Could not send right now - please try again.', $wantsJson);
}

/**
 * Minimal authenticated SMTP client (AUTH LOGIN).
 * Implicit SSL (465) or STARTTLS (587). $isHtml toggles the content type.
 */
function smtp_send($host, $port, $user, $pass, $from, $fromName, $to, $replyTo, $subject, $body, &$err, $isHtml = false) {
  $transport = ($port == 465) ? "ssl://$host:$port" : "tcp://$host:$port";
  $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
  $fp = @stream_socket_client($transport, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
  if (!$fp) { $err = "connect failed: $errstr ($errno)"; return false; }
  stream_set_timeout($fp, 20);

  $read = function () use ($fp) {
    $data = '';
    while (($line = fgets($fp, 515)) !== false) {
      $data .= $line;
      if (strlen($line) >= 4 && $line[3] === ' ') break;
    }
    return $data;
  };
  $put = function ($c) use ($fp) { fwrite($fp, $c . "\r\n"); };
  $ok = function ($resp, $code) use (&$err) {
    if (substr($resp, 0, 3) !== $code) { $err = trim($resp); return false; }
    return true;
  };

  if (!$ok($read(), '220')) { fclose($fp); return false; }
  $put('EHLO sipstreet7.com.au');
  if (!$ok($read(), '250')) { fclose($fp); return false; }

  if ($port == 587) {
    $put('STARTTLS');
    if (!$ok($read(), '220')) { fclose($fp); return false; }
    if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { $err = 'STARTTLS failed'; fclose($fp); return false; }
    $put('EHLO sipstreet7.com.au');
    if (!$ok($read(), '250')) { fclose($fp); return false; }
  }

  $put('AUTH LOGIN');
  if (!$ok($read(), '334')) { fclose($fp); return false; }
  $put(base64_encode($user));
  if (!$ok($read(), '334')) { fclose($fp); return false; }
  $put(base64_encode($pass));
  if (!$ok($read(), '235')) { fclose($fp); return false; }

  $put("MAIL FROM:<$from>");
  if (!$ok($read(), '250')) { fclose($fp); return false; }
  $put("RCPT TO:<$to>");
  if (!$ok($read(), '250')) { fclose($fp); return false; }
  $put('DATA');
  if (!$ok($read(), '354')) { fclose($fp); return false; }

  $headers  = "From: $fromName <$from>\r\n";
  $headers .= "To: <$to>\r\n";
  $headers .= "Reply-To: <$replyTo>\r\n";
  $headers .= 'Subject: ' . $subject . "\r\n";
  $headers .= "MIME-Version: 1.0\r\n";
  $headers .= 'Content-Type: ' . ($isHtml ? 'text/html' : 'text/plain') . "; charset=UTF-8\r\n";
  $headers .= 'Date: ' . date('r') . "\r\n";
  $bodyEsc = preg_replace('/^\./m', '..', $body);
  $put($headers . "\r\n" . $bodyEsc . "\r\n.");
  if (!$ok($read(), '250')) { fclose($fp); return false; }

  $put('QUIT');
  fclose($fp);
  return true;
}
