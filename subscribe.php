<?php
/**
 * Sip Street 7 — newsletter signup handler.
 * Receives the "Join the Street" form and emails the subscriber's
 * address to the shop inbox. Designed for Hostinger (PHP) hosting.
 *
 * To change where signups are delivered, edit $TO below.
 */

$TO = 'hello@sipstreet7.com.au';      // where signups are sent
$FROM = 'hello@sipstreet7.com.au';    // must be a real mailbox on this domain
$BRAND = 'Sip Street 7';

// --- Is the client expecting JSON (our fetch) or a plain browser (no-JS)? ---
$wantsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

function respond($ok, $error = '', $wantsJson = true) {
  if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    if (!$ok) http_response_code($error === 'Method not allowed' ? 405 : 422);
    echo json_encode(['ok' => $ok, 'error' => $error]);
  } else {
    // No-JS fallback: bounce back to the site with a status flag
    $flag = $ok ? 'subscribed=1' : 'subscribed=0';
    header('Location: /index.html?' . $flag . '#newsletter');
  }
  exit;
}

// --- Only POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  respond(false, 'Method not allowed', $wantsJson);
}

// --- Honeypot: real users never fill the hidden "website" field ---
if (!empty($_POST['website'])) {
  respond(true, '', $wantsJson); // silently accept so bots think they succeeded
}

// --- Validate email ---
$email = trim($_POST['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
  respond(false, 'Please enter a valid email address.', $wantsJson);
}

// --- Build & send the notification email ---
$when = date('Y-m-d H:i:s');
$ip   = $_SERVER['REMOTE_ADDR'] ?? 'n/a';
$subject = 'New newsletter signup — ' . $BRAND;
$body  = "A new subscriber joined the Sip Street list:\n\n";
$body .= "Email: $email\n";
$body .= "When:  $when\n";
$body .= "IP:    $ip\n";

$headers  = 'From: ' . $BRAND . ' <' . $FROM . ">\r\n";
$headers .= 'Reply-To: ' . $email . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= 'X-Mailer: PHP/' . phpversion();

$sent = @mail($TO, $subject, $body, $headers);

if ($sent) {
  respond(true, '', $wantsJson);
} else {
  respond(false, 'Could not send right now — please try again.', $wantsJson);
}
