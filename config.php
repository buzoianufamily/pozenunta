<?php
/* ============================================================
   BUZONNECT – Album foto nuntă Răzvan & Maria
   FIȘIER DE CONFIGURARE
   ------------------------------------------------------------
   Modifică DOAR valorile dintre ghilimele de mai jos.
   ============================================================ */

/* ---------- 1. BAZA DE DATE (din cPanel) ----------
   ATENȚIE: în cPanel numele bazei și al userului încep de obicei
   cu prefixul contului (ex: r140100buzo_). Pune EXACT ce vezi în
   secțiunea "MySQL Databases".                                   */
define('DB_HOST', 'localhost');
define('DB_NAME', 'r140100buzo_pozenunta');   // numele bazei de date
define('DB_USER', 'r140100buzo_pozenunta');    // userul bazei de date (verifică prefixul în cPanel)
define('DB_PASS', 'nuntarmb');                // parola bazei de date
define('DB_CHARSET', 'utf8mb4');

/* ---------- 2. DETALII NUNTĂ ---------- */
define('NUME_MIRE',    'Răzvan');
define('NUME_MIREASA', 'Maria');
define('DATA_NUNTII',  '22 august 2026');
define('SITE_URL',     'https://nunta.razvanbuzoianu.ro'); // folosit la codul QR

/* ---------- 3. USERUL SUPREM (admin) ----------
   Autentificarea se face cu datele de mai jos. Le schimbi DIRECT aici,
   în acest fișier (nu mai există opțiune în panou).             */
define('ADMIN_USER', 'Razvan');
define('ADMIN_PASS', 'R0765793713iPhone!');

/* ---------- 4. SETĂRI ÎNCĂRCARE ---------- */
// Dimensiunea maximă per fișier. Filmele se trimit pe bucăți, deci limita
// serverului pe o singură cerere nu contează aici — contează doar spațiul
// pe disc și răbdarea invitatului: 4 GB de pe 4G înseamnă aproape două ore.
// Pozele se micșorează automat pe telefon, deci rămân mici oricum.
define('MAX_FILE_SIZE', 4 * 1024 * 1024 * 1024);
// Lățimea miniaturilor generate pentru galerie (px). Mai mic = galerie mai rapidă.
define('THUMB_WIDTH', 600);
// Câte poze se încarcă odată în galerie (scroll infinit).
define('PER_PAGINA', 30);
// Spațiul TOTAL al găzduirii, în GB (pentru indicatorul din panou). Mărește-l dacă cumperi spațiu suplimentar.
define('DISK_QUOTA_GB', 80);

/* ---------- 5. CĂI (nu modifica) ---------- */
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('THUMB_DIR',  __DIR__ . '/uploads/thumbs/');
define('UPLOAD_URL', 'uploads/');
define('THUMB_URL',  'uploads/thumbs/');

/* ============================================================
   GATA. Nu este nevoie să modifici nimic mai jos.
   ============================================================ */

// Afișare erori dezactivată în producție (activează doar la depanare)
error_reporting(E_ALL);
ini_set('display_errors', '0');

/* ---------- Sesiune ----------
   PHP ține un blocaj exclusiv pe fișierul de sesiune cât durează cererea,
   deci două cereri ale aceluiași vizitator se așteaptă una pe alta. La
   încărcarea pe bucăți, unde plecă mai multe cereri deodată, asta le-ar
   pune la coadă degeaba.

   Un vizitator care nu are cookie de sesiune sigur nu este autentificat,
   deci nu-i pornim sesiune: fără fișier scris, fără blocaj. Sesiunea
   pornește doar pentru cine chiar are una (mirii) sau la cerere expresă
   (pagina de autentificare). */
function porneste_sesiune(bool $forteaza = false): void {
    if (session_status() !== PHP_SESSION_NONE) return;
    if (!$forteaza && empty($_COOKIE[session_name()])) return;
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

/* Eliberează blocajul mai devreme, când nu mai scriem în sesiune. */
function inchide_sesiune(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

porneste_sesiune();

// Conexiune PDO (reutilizabilă)
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            die('Eroare de conexiune la baza de date. Verifică datele din config.php.');
        }
    }
    return $pdo;
}
