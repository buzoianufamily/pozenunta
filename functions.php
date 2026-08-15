<?php
require_once __DIR__ . '/config.php';

/* ---------- Tipuri de fișiere permise ---------- */
function extensii_imagini(): array { return ['jpg','jpeg','png','gif','webp','heic','heif','bmp']; }
function extensii_video():   array { return ['mp4','mov','webm','m4v','3gp','ogg']; }
function extensii_permise(): array { return array_merge(extensii_imagini(), extensii_video()); }

/* ---------- Curățare text pentru afișare ---------- */
function h($text): string {
    return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------- Dimensiune lizibilă ---------- */
function format_marime(int $octeti): string {
    $u = ['B','KB','MB','GB','TB'];
    $i = 0;
    $n = (float)$octeti;
    while ($n >= 1024 && $i < count($u) - 1) { $n /= 1024; $i++; }
    return round($n, $n >= 10 || $i === 0 ? 0 : 1) . ' ' . $u[$i];
}

/* ============================================================
   SETĂRI (cheie/valoare) – editabile din panoul de admin
   ============================================================ */
function &_setari_store() { static $c = null; return $c; }

function setare(string $cheie, $implicit = null) {
    $c = &_setari_store();
    if ($c === null) {
        $c = [];
        try {
            foreach (db()->query('SELECT cheie, valoare FROM setari') as $r) {
                $c[$r['cheie']] = $r['valoare'];
            }
        } catch (Throwable $e) { /* tabela poate lipsi înainte de setup */ }
    }
    return array_key_exists($cheie, $c) ? $c[$cheie] : $implicit;
}

function salveaza_setare(string $cheie, string $valoare): void {
    $stmt = db()->prepare(
        'INSERT INTO setari (cheie, valoare) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE valoare = VALUES(valoare)'
    );
    $stmt->execute([$cheie, $valoare]);
    $c = &_setari_store();
    if (is_array($c)) { $c[$cheie] = $valoare; }
}

function moderare_activa(): bool {
    return setare('moderare', '0') === '1';
}

function mesaj_bun_venit(): string {
    $implicit = "Bun venit la nunta noastră! 🤍\n\nSuntem nespus de fericiți că sunteți alături de noi în această zi specială. "
        . "Surprindeți momentele așa cum le vedeți voi — un zâmbet, un dans, o îmbrățișare — și încărcați-le aici. "
        . "Fiecare fotografie a voastră devine o amintire de neprețuit pentru noi.\n\nVă mulțumim din suflet!";
    return setare('mesaj_bun_venit', $implicit);
}

/* ============================================================
   JETONUL INVITATULUI (ca să-și poată șterge propriile fișiere)
   ------------------------------------------------------------
   Invitatul nu are cont. La prima încărcare îi punem în telefon un
   cookie cu un cod secret. În baza de date salvăm DOAR amprenta
   codului (sha256), niciodată codul în sine — dacă cineva ar citi
   baza de date, tot nu ar putea șterge pozele nimănui.

   Cookie-ul e httpOnly: JavaScript nu îl poate citi, deci nu poate
   fi furat printr-un script străin strecurat în pagină.
   ============================================================ */
define('COOKIE_INVITAT', 'invitat');
define('COOKIE_INVITAT_ZILE', 400);

function jeton_invitat(bool $creeazaDacaLipsete = false): string {
    $j = (string)($_COOKIE[COOKIE_INVITAT] ?? '');
    if (preg_match('/^[a-f0-9]{32,64}$/', $j)) return $j;
    if (!$creeazaDacaLipsete) return '';

    try {
        $j = bin2hex(random_bytes(24));
    } catch (Throwable $e) {
        $j = hash('sha256', uniqid('', true) . mt_rand());
    }
    if (!headers_sent()) {
        setcookie(COOKIE_INVITAT, $j, [
            'expires'  => time() + COOKIE_INVITAT_ZILE * 86400,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
    }
    $_COOKIE[COOKIE_INVITAT] = $j;
    return $j;
}

/* Ce se salvează efectiv în baza de date. */
function amprenta_jeton(string $jeton): ?string {
    return $jeton === '' ? null : hash('sha256', $jeton);
}

/* ============================================================
   AUTENTIFICARE ADMIN
   ============================================================ */
/* Fără sesiune pornită nu există autentificare.
   Verificăm $_SESSION, nu session_status(): după inchide_sesiune() starea
   devine „inactiv", dar datele citite rămân disponibile — iar adminul
   trebuie să rămână admin până la finalul cererii. */
function este_admin(): bool {
    return isset($_SESSION) && !empty($_SESSION['admin']);
}

function cere_admin(): void {
    if (!este_admin()) {
        header('Location: login.php');
        exit;
    }
}

/* Token CSRF simplu pentru formularele din admin */
function csrf_token(): string {
    porneste_sesiune(true);   // aici chiar avem nevoie de sesiune
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_valid(?string $token): bool {
    return isset($_SESSION) && !empty($token) && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

/* ============================================================
   GENERARE MINIATURĂ (thumbnail) cu GD
   Returnează true dacă a creat miniatura, false dacă nu a putut
   (ex: format HEIC nesuportat de GD pe server).
   ============================================================ */
function creeaza_thumbnail(string $sursa, string $destinatie, int $latimeMax = THUMB_WIDTH): bool {
    if (!function_exists('imagecreatetruecolor')) return false;
    $info = @getimagesize($sursa);
    if ($info === false) return false;

    [$latime, $inaltime] = $info;
    $tip = $info[2];

    switch ($tip) {
        case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($sursa); break;
        case IMAGETYPE_PNG:  $img = @imagecreatefrompng($sursa);  break;
        case IMAGETYPE_GIF:  $img = @imagecreatefromgif($sursa);  break;
        case IMAGETYPE_BMP:  $img = function_exists('imagecreatefrombmp')  ? @imagecreatefrombmp($sursa)  : false; break;
        case IMAGETYPE_WEBP: $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sursa) : false; break;
        default: return false;
    }
    if (!$img) return false;

    // Corectează orientarea pe baza datelor EXIF (poze de pe telefon)
    if ($tip === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($sursa);
        if (!empty($exif['Orientation'])) {
            switch ((int)$exif['Orientation']) {
                case 3: $img = imagerotate($img, 180, 0); break;
                case 6: $img = imagerotate($img, -90, 0); break;
                case 8: $img = imagerotate($img,  90, 0); break;
            }
            $latime   = imagesx($img);
            $inaltime = imagesy($img);
        }
    }

    $scala = min(1, $latimeMax / max(1, $latime));
    $lN = max(1, (int)round($latime * $scala));
    $iN = max(1, (int)round($inaltime * $scala));

    $thumb = imagecreatetruecolor($lN, $iN);
    // fundal alb (pentru PNG/GIF cu transparență)
    $alb = imagecolorallocate($thumb, 255, 255, 255);
    imagefilledrectangle($thumb, 0, 0, $lN, $iN, $alb);
    imagecopyresampled($thumb, $img, 0, 0, 0, 0, $lN, $iN, $latime, $inaltime);

    $ok = imagejpeg($thumb, $destinatie, 82);
    imagedestroy($img);
    imagedestroy($thumb);
    return $ok;
}

/* URL-ul de afișat în galerie pentru o poză:
   miniatura dacă există, altfel originalul.                      */
function url_previzualizare(array $poza): string {
    $thumb = THUMB_DIR . $poza['nume_fisier'] . '.jpg';
    if (is_file($thumb)) {
        return THUMB_URL . rawurlencode($poza['nume_fisier']) . '.jpg';
    }
    return UPLOAD_URL . rawurlencode($poza['nume_fisier']);
}
function url_original(array $poza): string {
    return UPLOAD_URL . rawurlencode($poza['nume_fisier']);
}

/* Construiește un array „curat" pentru JSON.
   Amprenta invitatului NU pleacă niciodată către pagină — trimitem doar
   „alMeu", ca telefonul lui să știe unde să arate butonul de ștergere. */
function poza_pentru_json(array $p): array {
    static $amprentaMea = false;
    if ($amprentaMea === false) {
        $amprentaMea = amprenta_jeton(jeton_invitat(false));
    }
    $alMeu = $amprentaMea !== null
             && !empty($p['jeton'])
             && hash_equals((string)$p['jeton'], $amprentaMea);

    return [
        'id'        => (int)$p['id'],
        'tip'       => $p['tip'],
        'preview'   => url_previzualizare($p),
        'original'  => url_original($p),
        'nume'      => $p['nume_invitat'] ?: '',
        'mesaj'     => $p['mesaj'] ?: '',
        'aprecieri' => (int)($p['aprecieri'] ?? 0),
        'data'      => date('d.m.Y H:i', strtotime($p['data_incarcare'])),
        'alMeu'     => $alMeu,
    ];
}

/* ============================================================
   MIGRARE AUTOMATĂ A SCHEMEI (rulează o singură dată după update)
   Adaugă coloana de aprecieri dacă lipsește. MariaDB suportă
   „ADD COLUMN IF NOT EXISTS", iar versiunea o ținem în setări
   ca să nu verificăm la fiecare cerere.
   ============================================================ */
/* Verificări portabile: „ADD COLUMN IF NOT EXISTS" merge pe MariaDB,
   dar nu pe MySQL. Întrebăm catalogul, ca să meargă pe amândouă. */
function coloana_exista(string $tabela, string $coloana): bool {
    try {
        $st = db()->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $st->execute([$tabela, $coloana]);
        return (int)$st->fetchColumn() > 0;
    } catch (Throwable $e) { return true; }   // la dubiu, nu modificăm nimic
}

function index_exista(string $tabela, string $index): bool {
    try {
        $st = db()->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?'
        );
        $st->execute([$tabela, $index]);
        return (int)$st->fetchColumn() > 0;
    } catch (Throwable $e) { return true; }
}

function adauga_index(string $tabela, string $nume, string $coloane): void {
    if (index_exista($tabela, $nume)) return;
    try { db()->exec("CREATE INDEX `$nume` ON `$tabela` ($coloane)"); }
    catch (Throwable $e) { /* index deja existent sub alt nume */ }
}

function asigura_schema(): void {
    try {
        if ((int)setare('schema_v', '0') >= 5) return;

        if (!coloana_exista('poze', 'aprecieri')) {
            db()->exec('ALTER TABLE poze ADD COLUMN aprecieri INT UNSIGNED NOT NULL DEFAULT 0');
        }
        /* Amprenta invitatului care a încărcat fișierul. */
        if (!coloana_exista('poze', 'jeton')) {
            db()->exec('ALTER TABLE poze ADD COLUMN jeton VARCHAR(64) DEFAULT NULL');
        }
        /* Amprenta conținutului, pentru a nu primi aceeași poză de două ori. */
        if (!coloana_exista('poze', 'amprenta_fisier')) {
            db()->exec('ALTER TABLE poze ADD COLUMN amprenta_fisier VARCHAR(64) DEFAULT NULL');
        }

        db()->exec("CREATE TABLE IF NOT EXISTS urari (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            nume         VARCHAR(120) NOT NULL,
            mesaj        TEXT NOT NULL,
            aprobat      TINYINT(1) NOT NULL DEFAULT 1,
            ip           VARCHAR(45) DEFAULT NULL,
            data_creare  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_data (data_creare)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        /* Indexuri pentru galerie: fără ele, fiecare derulare sortează
           toată tabela. Se simte când sute de oameni se uită deodată. */
        adauga_index('poze',  'idx_galerie',   'aprobat, data_incarcare, id');
        adauga_index('poze',  'idx_apreciate', 'aprobat, aprecieri, data_incarcare');
        adauga_index('poze',  'idx_jeton',     'jeton');
        adauga_index('poze',  'idx_amprenta',  'amprenta_fisier');
        adauga_index('urari', 'idx_aprobat',   'aprobat, data_creare');

        salveaza_setare('schema_v', '5');
    } catch (Throwable $e) { /* tabela poate lipsi înainte de setup */ }
}

/* ============================================================
   FIȘIERE DUPLICATE
   ------------------------------------------------------------
   Amprenta se calculează din conținutul fișierului, nu din nume:
   aceeași poză trimisă sub alt nume tot duplicat este.

   Atenție la ce NU prinde: pozele sunt micșorate în telefon înainte
   de trimitere, iar două telefoane diferite produc fișiere ușor
   diferite din același original. Deci se prind sigur retrimiterile
   de pe același telefon — cazul obișnuit, când cineva selectează din
   greșeală aceleași poze a doua oară.
   ============================================================ */
function amprenta_fisier(string $cale): ?string {
    $h = @hash_file('sha256', $cale);
    return $h === false ? null : $h;
}

/* Întoarce id-ul fișierului identic deja existent, dacă există. */
function duplicat_existent(?string $amprenta): ?int {
    if ($amprenta === null || $amprenta === '') return null;
    try {
        $st = db()->prepare('SELECT id FROM poze WHERE amprenta_fisier = ? LIMIT 1');
        $st->execute([$amprenta]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int)$id;
    } catch (Throwable $e) { return null; }
}

/* ============================================================
   FOTOGRAFIA DE CUPLU (copertă) — opțională
   ============================================================ */
function cover_fisier(): string {
    return (string)setare('cover', '');
}
function are_cover(): bool {
    $f = cover_fisier();
    return $f !== '' && is_file(UPLOAD_DIR . $f);
}
function url_cover(): string {
    return are_cover() ? UPLOAD_URL . rawurlencode(cover_fisier()) : '';
}
