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
   AUTENTIFICARE ADMIN
   ============================================================ */
function este_admin(): bool {
    return !empty($_SESSION['admin']);
}

function cere_admin(): void {
    if (!este_admin()) {
        header('Location: login.php');
        exit;
    }
}

/* Token CSRF simplu pentru formularele din admin */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_valid(?string $token): bool {
    return !empty($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
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

/* Construiește un array „curat" pentru JSON */
function poza_pentru_json(array $p): array {
    return [
        'id'        => (int)$p['id'],
        'tip'       => $p['tip'],
        'preview'   => url_previzualizare($p),
        'original'  => url_original($p),
        'nume'      => $p['nume_invitat'] ?: '',
        'mesaj'     => $p['mesaj'] ?: '',
        'aprecieri' => (int)($p['aprecieri'] ?? 0),
        'data'      => date('d.m.Y H:i', strtotime($p['data_incarcare'])),
    ];
}

/* ============================================================
   MIGRARE AUTOMATĂ A SCHEMEI (rulează o singură dată după update)
   Adaugă coloana de aprecieri dacă lipsește. MariaDB suportă
   „ADD COLUMN IF NOT EXISTS", iar versiunea o ținem în setări
   ca să nu verificăm la fiecare cerere.
   ============================================================ */
function asigura_schema(): void {
    try {
        if ((int)setare('schema_v', '0') < 3) {
            db()->exec("ALTER TABLE poze ADD COLUMN IF NOT EXISTS aprecieri INT UNSIGNED NOT NULL DEFAULT 0");
            db()->exec("CREATE TABLE IF NOT EXISTS urari (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                nume         VARCHAR(120) NOT NULL,
                mesaj        TEXT NOT NULL,
                aprobat      TINYINT(1) NOT NULL DEFAULT 1,
                ip           VARCHAR(45) DEFAULT NULL,
                data_creare  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_data (data_creare)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            salveaza_setare('schema_v', '3');
        }
    } catch (Throwable $e) { /* tabela poate lipsi înainte de setup */ }
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
