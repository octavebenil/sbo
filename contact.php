<?php
// contact.php — traitement du formulaire Contact/Devis
// Valide les champs, vérifie anti‑spam (honeypot, question, délai), envoie un email et redirige vers index.html

// CONFIGURATION — À PERSONNALISER
$TO = getenv('SBO_CONTACT_TO') ?: 'changez-moi@example.com'; // Remplacez par votre email
$FROM = getenv('SBO_CONTACT_FROM') ?: 'sbo@localhost';       // Adresse From technique (évitez d'utiliser l'email visiteur)
$SUBJECT_PREFIX = 'SBO — Demande';
$MIN_DELAY_MS = 3000;            // Délai minimum entre affichage et soumission
$MAX_DELAY_MS = 2 * 24 * 60 * 60 * 1000; // Délai max (2 jours)
$ENABLE_LOG_FALLBACK = true;     // Écrit dans storage/messages.log si mail() échoue

// FONCTIONS UTILES
function redirect_with($ok) {
  $flag = $ok ? '1' : '0';
  header('Location: index.html?sent=' . $flag . '#contact');
  exit;
}
function clean_header($v) {
  return trim(preg_replace('/[\r\n]+/', ' ', (string)$v));
}
function ensure_dir($dir) {
  if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect_with(false);
}

// Données
$name    = trim((string)($_POST['name'] ?? ''));
$email   = trim((string)($_POST['email'] ?? ''));
$service = trim((string)($_POST['service'] ?? ''));
$budget  = trim((string)($_POST['budget'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
$human   = trim((string)($_POST['human'] ?? ''));
$hp      = trim((string)($_POST['company'] ?? '')); // honeypot
$ts      = (string)($_POST['ts'] ?? '');

// Anti‑spam
if ($hp !== '') { redirect_with(false); }
if ($human !== '5') { redirect_with(false); }
$now = (int) (microtime(true) * 1000);
$tsn = ctype_digit($ts) ? (int)$ts : $now;
$delta = $now - $tsn;
if ($delta < $MIN_DELAY_MS || $delta > $MAX_DELAY_MS) { redirect_with(false); }

// Validations
if (mb_strlen($name) < 2) { redirect_with(false); }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { redirect_with(false); }
if (mb_strlen($message) < 10) { redirect_with(false); }

// Préparation email
$subject = $SUBJECT_PREFIX . ' — ' . ($service !== '' ? $service : 'Contact') . '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$body = "Nom: {$name}\n"
      . "Email: {$email}\n"
      . "Service: {$service}\n"
      . "Budget: {$budget}\n"
      . "Message:\n{$message}\n\n"
      . "Meta: IP={$ip} | UA=" . substr($ua,0,300) . "\n"
      . "AntiSpam: human={$human} | deltaMs={$delta}\n";

$headers = [];
$headers[] = 'From: ' . clean_header($FROM);
$headers[] = 'Reply-To: ' . clean_header($email);
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers_str = implode("\r\n", $headers);

$sent = false;
// Tentative d'envoi mail
try {
  $sent = @mail($TO, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers_str);
} catch (Throwable $e) {
  $sent = false;
}

// Fallback en log
if (!$sent && $ENABLE_LOG_FALLBACK) {
  $dir = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
  ensure_dir($dir);
  $logFile = $dir . DIRECTORY_SEPARATOR . 'messages.log';
  $line = date('c') . ' | ' . str_replace(["\r","\n"],' ', $subject) . ' | ' . str_replace(["\r","\n"],' ', $body) . "\n";
  @file_put_contents($logFile, $line, FILE_APPEND);
  $sent = true; // Considérer comme géré
}

redirect_with($sent);
