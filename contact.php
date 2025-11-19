<?php
// === Helpers .env et accès variables ===
if (!function_exists('str_starts_with')){ function str_starts_with($h,$n){ return substr($h,0,strlen($n))===$n; } }
if (!function_exists('str_ends_with')){ function str_ends_with($h,$n){ return $n==='' || substr($h,-strlen($n))===$n; } }

// Chargement .env (facultatif)
function load_env_file($path){
  if (!is_file($path)) return;
  $raw = @file_get_contents($path);
  if ($raw === false) return;
  if (substr($raw, 0, 3) === "\xEF\xBB\xBF") { $raw = substr($raw, 3); }
  $lines = preg_split("/[\r\n]+/", $raw);
  foreach ($lines as $line){
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    $eq = strpos($line, '=');
    if ($eq === false) continue;
    $key = trim(substr($line, 0, $eq));
    $val = trim(substr($line, $eq + 1));
    if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, "'") && str_ends_with($val, "'"))){
      $val = substr($val, 1, -1);
    }
    if (getenv($key) === false && (!isset($_ENV[$key]) && !isset($_SERVER[$key]))){
      @putenv($key.'='.$val);
      $_ENV[$key] = $val;
      $_SERVER[$key] = $val;
    }
  }
}

// Accès variable d'environnement robuste
function env_get($key, $default = null){
  $v = getenv($key);
  if ($v !== false && $v !== '') return $v;
  if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
  if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
  return $default;
}

// Charger les variables depuis .env AVANT d'y accéder
load_env_file(__DIR__ . DIRECTORY_SEPARATOR . '.env');

// Affichage des erreurs (debug) — activer avec SBO_DEBUG=true dans .env
$__DEBUG = filter_var(env_get('SBO_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
if ($__DEBUG) {
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);
}

// PHPMailer via Composer si disponible
$composerAutoload = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_file($composerAutoload)) {
  require_once $composerAutoload;
}
// Import des classes (sans effet si non installées)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// CONFIGURATION — À PERSONNALISER
$TO = env_get('SBO_CONTACT_TO', 'changez-moi@example.com'); // Remplacez par votre email
$FROM = env_get('SBO_CONTACT_FROM', 'sbo@localhost');       // Adresse From technique (évitez d'utiliser l'email visiteur)
$SUBJECT_PREFIX = env_get('SBO_CONTACT_SUBJECT_PREFIX', 'SBO — Demande');
$MIN_DELAY_MS = (int) env_get('SBO_CONTACT_MIN_DELAY_MS', 3000);            // Délai minimum entre affichage et soumission
$MAX_DELAY_MS = (int) env_get('SBO_CONTACT_MAX_DELAY_MS', 2 * 24 * 60 * 60 * 1000); // Délai max (2 jours)
$ENABLE_LOG_FALLBACK = filter_var(env_get('SBO_CONTACT_ENABLE_LOG_FALLBACK', 'true'), FILTER_VALIDATE_BOOLEAN);     // Écrit dans storage/messages.log si mail() échoue

// Paramètres SMTP via variables d'environnement (.env possible)
$SMTP_ENABLE  = filter_var(env_get('SMTP_ENABLE', 'false'), FILTER_VALIDATE_BOOLEAN);
$SMTP_HOST    = env_get('SMTP_HOST', '');
$SMTP_PORT    = (int) env_get('SMTP_PORT', 587);
$SMTP_USER    = env_get('SMTP_USER', '');
$SMTP_PASS    = env_get('SMTP_PASS', '');
$SMTP_SECURE  = strtolower(env_get('SMTP_SECURE', 'tls')); // tls|ssl|none
$SMTP_FROM_N  = env_get('SMTP_FROM_NAME', 'SBO');
$SMTP_TIMEOUT = (int) env_get('SMTP_TIMEOUT', 15);

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

// Envoi via PHPMailer sera utilisé plus bas si disponible

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
$human_a = trim((string)($_POST['human_a'] ?? ''));
$human_b = trim((string)($_POST['human_b'] ?? ''));
$hp      = trim((string)($_POST['company'] ?? '')); // honeypot
$ts      = (string)($_POST['ts'] ?? '');

// Anti‑spam
if ($hp !== '') { redirect_with(false); }
// Validation dynamique: si human_a et human_b sont fournis et numériques, vérifier la somme
$has_dyn = ctype_digit($human_a) && ctype_digit($human_b);
if ($has_dyn) {
  $exp = ((int)$human_a) + ((int)$human_b);
  if ((string)$exp !== $human) { redirect_with(false); }
} else {
  // Fallback legacy (3 + 2)
  if ($human !== '5') { redirect_with(false); }
}
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
// Tentative d'envoi via SMTP (PHPMailer) si activé et disponible, sinon fallback sur mail()
if ($SMTP_ENABLE && $SMTP_HOST && $SMTP_PORT && class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
  try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $SMTP_HOST;
    $mail->Port       = (int)$SMTP_PORT;
    // Sécurité
    if ($SMTP_SECURE === 'ssl') {
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($SMTP_SECURE === 'tls') {
      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }
    // Auth
    $mail->SMTPAuth   = ($SMTP_USER !== '' || $SMTP_PASS !== '');
    if ($mail->SMTPAuth) {
      $mail->Username = $SMTP_USER;
      $mail->Password = $SMTP_PASS;
    }
    $mail->Timeout    = (int)$SMTP_TIMEOUT;
    $mail->CharSet    = 'UTF-8';

    // Expéditeur / destinataire
    $mail->setFrom($FROM, $SMTP_FROM_N ?: 'SBO');
    $mail->addAddress($TO);
    if ($email) { $mail->addReplyTo($email, $name ?: $email); }

    // Contenu
    $mail->Subject = $subject;
    $mail->isHTML(true);
    $mail->Body    = nl2br($body);
    $mail->AltBody = $body;

    $sent = $mail->send();
  } catch (Throwable $e) {
    $sent = false;
    // Journaliser l'erreur SMTP pour diagnostic
    @error_log('SMTP send error: ' . $e->getMessage());
  }
} else {
  // Environnement sans SMTP/PHPMailer: utilisation de mail()
  $sent = @mail($TO, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers_str);
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
