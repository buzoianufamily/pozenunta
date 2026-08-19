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

/* ---------- Limitele reale de încărcare ----------
   Nu presupunem nimic: le citim de la server. Pe cPanel valorile se pot
   seta pe fiecare domeniu în parte, deci pot să difere de la un site la
   altul din același cont. */
function octeti_din_ini(string $val): int {
    $val = trim($val);
    if ($val === '' || $val === '-1') return 0;          // 0 = fără limită
    $u = strtolower($val[strlen($val) - 1]);
    $n = (float)$val;
    switch ($u) {
        case 'g': $n *= 1024; // fall through
        case 'm': $n *= 1024; // fall through
        case 'k': $n *= 1024;
    }
    return (int)$n;
}

/* Cât acceptă serverul într-o SINGURĂ cerere. */
function limita_pe_cerere(): int {
    $umf = octeti_din_ini((string)ini_get('upload_max_filesize'));
    $pms = octeti_din_ini((string)ini_get('post_max_size'));
    $v = array_filter([$umf, $pms]);                      // ignorăm „fără limită"
    return empty($v) ? 64 * 1024 * 1024 : (int)min($v);
}

/* Mărimea unei bucăți la încărcarea filmelor mari.
   Bucăți mai mari înseamnă mai puține cereri, deci mai puțină apăsare pe
   server — dar trebuie să încapă într-o cerere, altfel toate eșuează. */
function dimensiune_bucata(): int {
    $sigur = (int)(limita_pe_cerere() * 0.8);             // lăsăm loc pentru antet
    return max(1024 * 1024, min(8 * 1024 * 1024, $sigur));
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

/* ============================================================
   TEXTELE DE PE PAGINI, schimbabile din panou
   ------------------------------------------------------------
   Toate stau într-un singur loc: cheia, unde apare, cum se
   numește în panou și ce scrie dacă nu s-a schimbat nimic.
   Ca să adaugi un text nou, îl pui aici și îl chemi cu text().
   ============================================================ */
function texte_editabile(): array {
    return [
        /* ---------- meniul de sus (pe toate paginile) ---------- */
        'tx_meniu_acasa'     => ['Meniu', 'Prima legătură (pagina de încărcare)', 'Încarcă poze sau clipuri', false],
        'tx_meniu_galerie'   => ['Meniu', 'A doua legătură (galeria)', 'Galerie', false],
        'tx_meniu_urari'     => ['Meniu', 'A treia legătură (cartea de urări)', 'Carte de urări', false],

        /* ---------- prima pagină ---------- */
        'tx_acasa_eyebrow'   => ['Prima pagină', 'Rândul mic de deasupra numelor', 'Albumul nostru de nuntă', false],
        'tx_incarca_titlu'   => ['Prima pagină', 'Titlul secțiunii de încărcare', 'Împărtășește momentele', false],
        'tx_incarca_desc'    => ['Prima pagină', 'Textul de sub titlu', 'Ai surprins un moment frumos? Încarcă-l aici — pozele și filmele tale ajung direct în albumul nostru. Nu e nevoie de cont.', true],
        'tx_drop_titlu'      => ['Prima pagină', 'Textul mare din chenarul de încărcare', 'Încarcă pozele și videoclipurile aici', false],
        'tx_drop_desc'       => ['Prima pagină', 'Textul mic din chenar', 'Poți selecta oricâte deodată, direct de pe telefon', false],
        'tx_nume_eticheta'   => ['Prima pagină', 'Eticheta câmpului cu numele', 'Numele tău', false],
        'tx_nume_ajutor'     => ['Prima pagină', 'Îndemnul de sub câmpul cu numele', 'Scrie-ți numele ca să știm ale cui sunt pozele — opțional, dar ne-ar bucura mult 🤍', true],
        'tx_buton_incarca'   => ['Prima pagină', 'Textul butonului de încărcare', 'Încarcă în album', false],
        'tx_succes_titlu'    => ['Prima pagină', 'Titlul de mulțumire, după încărcare', 'Mulțumim din suflet!', false],
        'tx_recente_titlu'   => ['Prima pagină', 'Titlul benzii cu amintiri', 'Amintiri de la voi', false],
        'tx_urari_titlu'     => ['Prima pagină', 'Titlul secțiunii cu urări', 'Urări de la invitați', false],

        /* ---------- galerie ---------- */
        'tx_gal_eyebrow'     => ['Galerie', 'Rândul mic de deasupra titlului', 'Amintirile noastre', false],
        'tx_gal_titlu'       => ['Galerie', 'Titlul paginii', 'Galeria nunții', false],
        'tx_gal_gol'         => ['Galerie', 'Ce scrie când albumul e gol', 'Albumul așteaptă primele voastre fotografii.', true],

        /* ---------- carte de urări ---------- */
        'tx_ur_eyebrow'      => ['Carte de urări', 'Rândul mic de deasupra titlului', 'Gândurile voastre', false],
        'tx_ur_titlu'        => ['Carte de urări', 'Titlul paginii', 'Carte de urări', false],
        'tx_ur_nume'         => ['Carte de urări', 'Eticheta câmpului cu numele', 'Numele tău', false],
        'tx_ur_mesaj'        => ['Carte de urări', 'Eticheta câmpului cu urarea', 'Urarea ta', false],
        'tx_ur_buton'        => ['Carte de urări', 'Textul butonului', 'Trimite urarea', false],
        'tx_ur_multumim'     => ['Carte de urări', 'Mulțumirea de după trimitere', 'Îți mulțumim din suflet pentru urare! 🤍', true],
        'tx_ur_gol'          => ['Carte de urări', 'Ce scrie când nu există urări', 'Fii primul care lasă o urare frumoasă pentru miri.', true],
    ];
}

/* Textul de afișat: cel schimbat din panou, altfel cel de pornire. */
function text(string $cheie): string {
    $toate = texte_editabile();
    $implicit = $toate[$cheie][2] ?? '';
    $v = setare($cheie, null);
    return ($v === null || $v === '') ? $implicit : (string)$v;
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
   MINIATURI
   ------------------------------------------------------------
   Întâi cu GD, care e rapid și ajunge pentru JPEG și PNG. Dacă nu
   reușește — cazul tipic fiind HEIC-ul de pe iPhone, pe care GD nu
   îl poate citi — încercăm cu Imagick, dacă serverul îl are.

   Contează pentru că pozele de pe iPhone se transformă de obicei în
   telefon înainte de trimitere; dar pe un telefon vechi sau cu
   memoria plină transformarea poate să nu reușească, iar atunci
   fișierul ajunge aici așa cum e.
   ============================================================ */
function imagick_poate_heic(): bool {
    static $poate = null;
    if ($poate !== null) return $poate;
    if (!extension_loaded('imagick') || !class_exists('Imagick')) return $poate = false;
    try {
        $f = @Imagick::queryFormats('HEIC') ?: [];
        $g = @Imagick::queryFormats('HEIF') ?: [];
        return $poate = (!empty($f) || !empty($g));
    } catch (Throwable $e) { return $poate = false; }
}

/* Miniatură cu Imagick. Îi punem frâu la memorie, ca o poză uriașă
   să nu tragă serverul după ea. */
function thumbnail_imagick(string $sursa, string $destinatie, int $latimeMax): bool {
    if (!extension_loaded('imagick') || !class_exists('Imagick')) return false;
    try {
        $im = new Imagick();
        $im->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024);
        $im->setResourceLimit(Imagick::RESOURCETYPE_MAP,    256 * 1024 * 1024);
        $im->readImage($sursa);
        if ($im->getNumberImages() > 1) { $im->setIteratorIndex(0); }
        $im = $im->coalesceImages();
        if (method_exists($im, 'autoOrient')) { @$im->autoOrient(); }   // rotirea din EXIF
        $im->setImageFormat('jpeg');
        $im->setImageCompressionQuality(82);
        $im->thumbnailImage($latimeMax, 0);
        $im->stripImage();                                             // scoate datele EXIF
        $ok = $im->writeImage($destinatie);
        $im->clear(); $im->destroy();
        return (bool)$ok;
    } catch (Throwable $e) { return false; }
}

/* Fără folderul de miniaturi, GD și Imagick nu au unde scrie și dau greș
   în tăcere — iar galeria începe să trimită originalele: 1,2 MB în loc de
   55 KB de fiecare poză, fără ca nimeni să vadă că s-a stricat ceva.
   Îl facem dacă lipsește, ca o mutare greșită din File Manager sau o
   copiere care sare folderele goale să nu coste toată seara. */
function asigura_folder_miniaturi(): bool {
    if (is_dir(THUMB_DIR)) return is_writable(THUMB_DIR);
    if (!@mkdir(THUMB_DIR, 0755, true) && !is_dir(THUMB_DIR)) return false;
    return is_writable(THUMB_DIR);
}

function creeaza_thumbnail(string $sursa, string $destinatie, int $latimeMax = THUMB_WIDTH): bool {
    if (strpos($destinatie, THUMB_DIR) === 0) asigura_folder_miniaturi();
    if (thumbnail_gd($sursa, $destinatie, $latimeMax)) return true;
    return thumbnail_imagick($sursa, $destinatie, $latimeMax);
}

function thumbnail_gd(string $sursa, string $destinatie, int $latimeMax = THUMB_WIDTH): bool {
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

/* ------------------------------------------------------------
   HEIC → JPEG, pe server
   Un fișier HEIC lăsat așa se vede pe iPhone, dar NU pe Android sau
   pe calculator. Dacă telefonul nu l-a transformat înainte de
   trimitere, îl transformăm noi — altfel jumătate dintre invitați ar
   vedea un pătrat gol în galerie.
   Întoarce noua cale dacă a transformat, altfel null.
   ------------------------------------------------------------ */
function converteste_heic(string $cale): ?string {
    $ext = strtolower(pathinfo($cale, PATHINFO_EXTENSION));
    if ($ext !== 'heic' && $ext !== 'heif') return null;
    if (!imagick_poate_heic()) return null;

    $nou = preg_replace('/\.(heic|heif)$/i', '.jpg', $cale);
    if (!$nou || $nou === $cale) return null;

    try {
        $im = new Imagick();
        $im->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 512 * 1024 * 1024);
        $im->setResourceLimit(Imagick::RESOURCETYPE_MAP,    512 * 1024 * 1024);
        $im->readImage($cale);
        if ($im->getNumberImages() > 1) { $im->setIteratorIndex(0); }
        $im = $im->coalesceImages();
        if (method_exists($im, 'autoOrient')) { @$im->autoOrient(); }
        $im->setImageFormat('jpeg');
        $im->setImageCompressionQuality(88);   // e originalul, nu miniatura
        $ok = $im->writeImage($nou);
        $im->clear(); $im->destroy();
        if (!$ok || !is_file($nou)) return null;
    } catch (Throwable $e) { return null; }

    @unlink($cale);          // originalul nu mai e de folos nimănui
    @chmod($nou, 0644);
    return $nou;
}

/* URL-ul de afișat în galerie pentru o poză:
   miniatura dacă există, altfel originalul.                      */
/* Are fișierul o miniatură adevărată pe disc?
   La filme se face din cadrul trimis de telefon; dacă telefonul nu a
   reușit să-l scoată, miniatura lipsește — iar atunci galeria trebuie
   să arate filmul însuși, nu să încerce să-l pună într-o imagine. */
function are_miniatura(array $poza): bool {
    return is_file(THUMB_DIR . $poza['nume_fisier'] . '.jpg');
}

function url_previzualizare(array $poza): string {
    if (are_miniatura($poza)) {
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
        'miniatura' => are_miniatura($p),
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
        if ((int)setare('schema_v', '0') >= 6) return;

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
        /* Proprietarul urării, ca invitatul să și-o poată șterge. */
        if (!coloana_exista('urari', 'jeton')) {
            db()->exec('ALTER TABLE urari ADD COLUMN jeton VARCHAR(64) DEFAULT NULL');
        }

        /* Aprecierile se numără pe server, câte una per invitat și poză.
           Cheia primară dublă face imposibilă aprecierea de două ori. */
        db()->exec("CREATE TABLE IF NOT EXISTS aprecieri (
            poza_id     INT NOT NULL,
            jeton       VARCHAR(64) NOT NULL,
            data_creare TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (poza_id, jeton),
            INDEX idx_poza (poza_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        adauga_index('poze',  'idx_jeton',     'jeton');
        adauga_index('poze',  'idx_amprenta',  'amprenta_fisier');
        adauga_index('urari', 'idx_aprobat',   'aprobat, data_creare');
        adauga_index('urari', 'idx_jeton',     'jeton');

        salveaza_setare('schema_v', '6');
    } catch (Throwable $e) { /* tabela poate lipsi înainte de setup */ }
}

/* Ce a apreciat deja invitatul, dintr-o listă de poze. O singură
   interogare pentru toată pagina, nu una per poză. */
function aprecieri_mele(array $ids): array {
    $amprenta = amprenta_jeton(jeton_invitat(false));
    if ($amprenta === null || empty($ids)) return [];
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $sem = implode(',', array_fill(0, count($ids), '?'));
    try {
        $st = db()->prepare("SELECT poza_id FROM aprecieri WHERE jeton = ? AND poza_id IN ($sem)");
        $st->execute(array_merge([$amprenta], $ids));
        return array_flip(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
    } catch (Throwable $e) { return []; }
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
/* Peste atât nu mai citim tot fișierul ca să-i luăm amprenta. */
define('AMPRENTA_INTEGRAL_MAX', 8 * 1024 * 1024);

function amprenta_fisier(string $cale): ?string {
    $dim = @filesize($cale);
    if ($dim === false) return null;

    /* Pozele — și acolo se întâmplă retrimiterile — se citesc întregi:
       e ieftin și prinde orice. */
    if ($dim <= AMPRENTA_INTEGRAL_MAX) {
        $h = @hash_file('sha256', $cale);
        return $h === false ? null : $h;
    }

    /* Filmele nu: un film de 500 MB citit integral ține un proces ocupat
       peste două secunde numai ca să afle ceva ce se vede și din primii
       megaocteți. Luăm începutul plus mărimea exactă — două filme
       diferite care încep la fel ȘI au exact aceeași mărime până la
       ultimul octet practic nu există. */
    $f = @fopen($cale, 'rb');
    if (!$f) return null;
    $ctx = hash_init('sha256');
    hash_update($ctx, 'm' . $dim . ':');
    hash_update_stream($ctx, $f, AMPRENTA_INTEGRAL_MAX);
    fclose($f);
    return hash_final($ctx);
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
/* Coperta vine din panou așa cum a ieșit din aparat: poate avea 4000 de
   puncte pe lățime și câteva zeci de MB. Pe pagină intră într-o ramă de
   520 de puncte, deci tot ce trece prin rețea peste atât e doar timp de
   așteptare — și e prima poză de pe prima pagină, așa că browserul ține
   rotița pornită până o termină.

   Facem o dată o variantă mică, o ținem lângă miniaturi și o refolosim.
   Dacă nu se poate face (fișier ciudat, și GD și Imagick dau greș), ne
   întoarcem la original: pagina arată la fel, doar se încarcă greu, ca
   înainte. */
function cover_mic(): string {
    static $rezultat = null;
    if ($rezultat !== null) return $rezultat;

    $f = cover_fisier();
    if ($f === '' || !is_file(UPLOAD_DIR . $f)) return $rezultat = '';

    $dest = THUMB_DIR . $f . '.jpg';
    if (is_file($dest)) return $rezultat = $f . '.jpg';

    if (!is_dir(THUMB_DIR)) @mkdir(THUMB_DIR, 0755, true);
    if (creeaza_thumbnail(UPLOAD_DIR . $f, $dest, COVER_WIDTH)) {
        @chmod($dest, 0644);
        return $rezultat = $f . '.jpg';
    }
    return $rezultat = '';
}

function url_cover(): string {
    if (!are_cover()) return '';
    $mic = cover_mic();
    return $mic !== ''
        ? THUMB_URL . rawurlencode($mic)
        : UPLOAD_URL . rawurlencode(cover_fisier());
}

/* Șterge și varianta mică atunci când coperta e înlocuită sau scoasă,
   altfel rămâne pe disc și, mai rău, ar fi refolosită pentru alt fișier
   cu același nume. */
function sterge_cover_mic(string $fisier): void {
    if ($fisier === '') return;
    $mic = THUMB_DIR . $fisier . '.jpg';
    if (is_file($mic)) @unlink($mic);
}
