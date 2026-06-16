<?php
/**
 * Sip Street 7 — newsletter signup handler (authenticated SMTP).
 *
 * Hostinger silently drops PHP mail(), so this sends through your mailbox
 * via SMTP instead. Self-contained — no external libraries.
 *
 * SETUP (do this on the server, NOT in the public repo):
 *   1. Upload this file to public_html.
 *   2. Create a file called  secrets.php  next to it containing ONE line:
 *        <?php $SMTP_PASS = 'your-hello@-mailbox-password';
 *      (This keeps your password out of the public GitHub repo.)
 */

// ===== CONFIG =====
$TO        = 'hello@sipstreet7.com.au';   // where signups are delivered
$SMTP_USER = 'hello@sipstreet7.com.au';   // your mailbox (login + From)
$SMTP_HOST = 'smtp.hostinger.com';        // Hostinger outgoing server
$SMTP_PORT = 465;                         // 465 = SSL. If blocked, try 587 (STARTTLS)
$BRAND     = 'Sip Street 7';

// Password is loaded from secrets.php (gitignored) so it never hits the repo.
$SMTP_PASS = '';
@include __DIR__ . '/secrets.php';
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

// Honeypot — real users never fill the hidden "website" field
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

// ----- Build the message -----
$subject = 'New newsletter signup - ' . $BRAND;
$body  = "A new subscriber joined the Sip Street list:\r\n\r\n";
$body .= "Email: $email\r\n";
$body .= "When:  " . date('Y-m-d H:i:s') . "\r\n";
$body .= "IP:    " . ($_SERVER['REMOTE_ADDR'] ?? 'n/a') . "\r\n";

$err = '';
$ok = smtp_send($SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_USER, $BRAND, $TO, $email, $subject, $body, $err);

if ($ok) {
  respond(true, '', $wantsJson);
} else {
  // $err is logged server-side; the visitor only sees a friendly message
  error_log('[subscribe.php] SMTP error: ' . $err);
  respond(false, 'Could not send right now - please try again.', $wantsJson);
}

/**
 * Minimal authenticated SMTP client (AUTH LOGIN).
 * Supports implicit SSL (port 465) and STARTTLS (port 587).
 */
function smtp_send($host, $port, $user, $pass, $from, $fromName, $to, $replyTo, $subject, $body, &$err) {
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
  if (!$ok($read(), '235')) { fclose($fp); return false; } // 235 = auth ok

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
  $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
  $headers .= 'Date: ' . date('r') . "\r\n";
  $bodyEsc = preg_replace('/^\./m', '..', $body); // dot-stuffing
  $put($headers . "\r\n" . $bodyEsc . "\r\n.");
  if (!$ok($read(), '250')) { fclose($fp); return false; }

  $put('QUIT');
  fclose($fp);
  return true;
}
