<?php
/* ============================================================
   ÎNCĂRCARE PE BUCĂȚI, CU RELUARE
   ------------------------------------------------------------
   De ce există: serverul acceptă fișiere de 1 GB, dar are
   max_input_time = 300s. Un film mare urcat de pe telefon pe 4G
   depășește acest timp și cade — iar o reîncercare „de la zero"
   cade din nou. Aici fișierul vine în bucăți mici (fiecare
   trimitere durează secunde), care se lipesc într-un fișier
   temporar. Dacă pică netul sau se închide telefonul, clientul
   întreabă „cât ai primit?" și continuă exact de acolo.

   Acțiuni (POST):
     stare       → id                       ⇒ câți octeți s-au primit
     bucata      → id, offset, fișier       ⇒ adaugă bucata
     finalizeaza → id, name, nume, mesaj…   ⇒ mută în album + BD
   ============================================================ */
require_once __DIR__ . '/functions.php';
header('Content-Type: application/json; charset=utf-8');
inchide_sesiune();   // bucățile nu ating sesiunea; fără blocaj, urcă în paralel

/* Atenție: NU atingem baza de date la „stare" și „bucata". Un film de
   1 GB înseamnă ~250 de bucăți; dacă fiecare ar deschide o conexiune,
   am încărca degeaba serverul de baze de date exact când 100 de
   invitați urcă simultan. Baza de date e folosită doar la finalizare. */

/* Folderul pentru bucăți, ținut în afara albumului. */
define('PARTI_DIR', UPLOAD_DIR . '.parti/');

/* Bucățile abandonate se șterg după atâtea ore. */
define('PARTI_EXPIRA_ORE', 48);

function raspunde(array $date, int $cod = 200): never {
    http_response_code($cod);
    echo json_encode($date, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    raspunde(['ok' => false, 'eroare' => 'Metodă invalidă.'], 405);
}

/* ------------------------------------------------------------
   Pregătește folderul de bucăți și îl protejează.
   ------------------------------------------------------------ */
function pregateste_parti(): bool {
    if (!is_dir(PARTI_DIR)) {
        if (!@mkdir(PARTI_DIR, 0755, true) && !is_dir(PARTI_DIR)) return false;
    }
    $ht = PARTI_DIR . '.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Require all denied\n");
    }
    return is_writable(PARTI_DIR);
}

/* ------------------------------------------------------------
   Identificatorul bucății vine de la client. Îl acceptăm DOAR
   ca text hexazecimal, ca să nu poată ieși din folder
   (fără „../", fără nume ciudate).
   ------------------------------------------------------------ */
function id_valid(string $id): bool {
    return (bool)preg_match('/^[a-f0-9]{16,64}$/', $id);
}

function cale_parte(string $id): string {
    return PARTI_DIR . $id . '.part';
}

/* Câți octeți avem deja pentru acest fișier. */
function octeti_primiti(string $id): int {
    $c = cale_parte($id);
    clearstatcache(true, $c);
    return is_file($c) ? (int)filesize($c) : 0;
}

/* ------------------------------------------------------------
   Curăță bucățile abandonate (rulează rar, ca să nu încetinească).
   ------------------------------------------------------------ */
function curata_parti_vechi(): void {
    if (random_int(1, 50) !== 1) return;          // ~2% din cereri
    if (!is_dir(PARTI_DIR)) return;
    $limita = time() - PARTI_EXPIRA_ORE * 3600;
    foreach ((array)@glob(PARTI_DIR . '*.part') as $f) {
        if (@filemtime($f) < $limita) @unlink($f);
    }
}

if (!pregateste_parti()) {
    raspunde(['ok' => false, 'eroare' => 'Nu se poate scrie pe server. Anunță organizatorul.'], 500);
}
curata_parti_vechi();

$actiune = (string)($_POST['actiune'] ?? '');
$id      = strtolower(trim((string)($_POST['id'] ?? '')));

if (!id_valid($id)) {
    raspunde(['ok' => false, 'eroare' => 'Identificator invalid.'], 400);
}

/* ============================================================
   1) STARE — de unde reluăm?
   ============================================================ */
if ($actiune === 'stare') {
    raspunde(['ok' => true, 'primit' => octeti_primiti($id)]);
}

/* ============================================================
   2) BUCATA — lipește următoarea felie
   ============================================================ */
if ($actiune === 'bucata') {
    $offset = (int)($_POST['offset'] ?? -1);
    if ($offset < 0) {
        raspunde(['ok' => false, 'eroare' => 'Poziție invalidă.'], 400);
    }
    if (empty($_FILES['bucata']) || ($_FILES['bucata']['error'] ?? 1) !== UPLOAD_ERR_OK
        || !is_uploaded_file($_FILES['bucata']['tmp_name'])) {
        raspunde(['ok' => false, 'eroare' => 'Bucată lipsă.'], 400);
    }

    $dimBucata = (int)$_FILES['bucata']['size'];
    $cale      = cale_parte($id);

    /* Clientul ne spune de la început cât are tot fișierul. Dacă nu
       încape, îl oprim la prima bucată — altfel ar fi urcat degeaba
       zeci de minute ca să afle abia la sfârșit. */
    $total = (int)($_POST['total'] ?? 0);
    if ($total > 0 && $total > MAX_FILE_SIZE) {
        @unlink($cale);
        raspunde(['ok' => false, 'preaMare' => true,
                  'eroare' => 'Filmul are ' . format_marime($total) . ', iar limita este '
                              . format_marime(MAX_FILE_SIZE) . '.'], 413);
    }

    /* Nu lăsăm un fișier să crească peste limita aplicației. */
    if ($offset + $dimBucata > MAX_FILE_SIZE) {
        @unlink($cale);
        raspunde(['ok' => false, 'eroare' => 'Fișierul depășește limita de ' . format_marime(MAX_FILE_SIZE) . '.'], 413);
    }

    $fp = @fopen($cale, 'c+b');
    if (!$fp) {
        raspunde(['ok' => false, 'eroare' => 'Nu se poate scrie pe server.'], 500);
    }

    /* Blocăm fișierul: două cereri nu trebuie să scrie simultan. */
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        raspunde(['ok' => false, 'eroare' => 'Server ocupat, reîncearcă.'], 503);
    }

    clearstatcache(true, $cale);
    $dimActuala = (int)filesize($cale);

    /* Dacă poziția cerută nu se potrivește cu ce avem, NU scriem —
       am face o gaură în fișier. Îi spunem clientului adevărul și
       el reia de la poziția corectă. */
    if ($offset !== $dimActuala) {
        flock($fp, LOCK_UN);
        fclose($fp);
        raspunde(['ok' => false, 'desincronizat' => true, 'primit' => $dimActuala]);
    }

    $sursa = @fopen($_FILES['bucata']['tmp_name'], 'rb');
    if (!$sursa) {
        flock($fp, LOCK_UN); fclose($fp);
        raspunde(['ok' => false, 'eroare' => 'Bucată ilizibilă.'], 500);
    }

    fseek($fp, $offset);
    $scrisi = stream_copy_to_stream($sursa, $fp);
    fflush($fp);
    fclose($sursa);
    flock($fp, LOCK_UN);
    fclose($fp);

    if ($scrisi === false || $scrisi < $dimBucata) {
        /* Scriere parțială — de obicei disc plin. Ce a apucat să intre e
           bun și e lipit corect, deci nu tăiem nimic: îi spunem clientului
           cât avem cu adevărat, iar el reia exact de acolo. */
        clearstatcache(true, $cale);
        raspunde(['ok' => false, 'eroare' => 'Spațiu insuficient pe server.', 'primit' => octeti_primiti($id)], 507);
    }

    clearstatcache(true, $cale);
    raspunde(['ok' => true, 'primit' => (int)filesize($cale)]);
}

/* ============================================================
   3) FINALIZEAZĂ — mută în album și scrie în baza de date
   ============================================================ */
if ($actiune === 'finalizeaza') {
    $cale = cale_parte($id);
    if (!is_file($cale)) {
        raspunde(['ok' => false, 'eroare' => 'Fișierul nu a fost găsit pe server. Ia-o de la capăt.'], 404);
    }

    $numeOriginal = trim((string)($_POST['name'] ?? ''));
    $nume         = mb_substr(trim((string)($_POST['nume']  ?? '')), 0, 120);
    $mesaj        = mb_substr(trim((string)($_POST['mesaj'] ?? '')), 0, 1000);

    $ext = strtolower(pathinfo($numeOriginal, PATHINFO_EXTENSION));
    if (!in_array($ext, extensii_permise(), true)) {
        @unlink($cale);
        raspunde(['ok' => false, 'eroare' => 'Tip de fișier neacceptat.'], 415);
    }

    clearstatcache(true, $cale);
    $dim = (int)filesize($cale);
    if ($dim <= 0 || $dim > MAX_FILE_SIZE) {
        @unlink($cale);
        raspunde(['ok' => false, 'eroare' => 'Dimensiune neacceptată.'], 413);
    }

    $tip = in_array($ext, extensii_video(), true) ? 'video' : 'imagine';

    /* Există deja exact acest fișier? Aruncăm bucata adunată și
       răspundem împăciuitor: pentru invitat, treaba e făcută. */
    $amprentaFis = amprenta_fisier($cale);
    if (duplicat_existent($amprentaFis) !== null) {
        @unlink($cale);
        raspunde(['ok' => true, 'reusite' => 0, 'duplicat' => true, 'moderare' => moderare_activa()]);
    }

    try {
        $numeFisier = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    } catch (Throwable $e) {
        $numeFisier = date('Ymd') . '_' . uniqid('', true) . '.' . $ext;
    }
    $destinatie = UPLOAD_DIR . $numeFisier;

    if (!@rename($cale, $destinatie)) {
        raspunde(['ok' => false, 'eroare' => 'Nu s-a putut salva în album.'], 500);
    }
    @chmod($destinatie, 0644);

    /* HEIC nevăzut de Android: îl transformăm noi, dacă telefonul n-a putut. */
    $convertit = converteste_heic($destinatie);
    if ($convertit !== null) {
        $destinatie = $convertit;
        $numeFisier = basename($convertit);
    }

    /* Miniatura: imaginile din fișierul propriu-zis, filmele din
       posterul trimis de telefon. */
    if ($tip === 'imagine') {
        @creeaza_thumbnail($destinatie, THUMB_DIR . $numeFisier . '.jpg');
    } elseif (!empty($_FILES['poster']) && (($_FILES['poster']['error'] ?? 1) === UPLOAD_ERR_OK)
              && is_uploaded_file($_FILES['poster']['tmp_name'])) {
        @creeaza_thumbnail($_FILES['poster']['tmp_name'], THUMB_DIR . $numeFisier . '.jpg');
    }

    try {
        asigura_schema();
        $stmt = db()->prepare(
            'INSERT INTO poze (nume_fisier, nume_original, nume_invitat, mesaj, tip, marime, aprobat, ip, jeton, amprenta_fisier)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $numeFisier,
            mb_substr($numeOriginal, 0, 255),
            $nume !== ''  ? $nume  : null,
            $mesaj !== '' ? $mesaj : null,
            $tip,
            $dim,
            moderare_activa() ? 0 : 1,
            $_SERVER['REMOTE_ADDR'] ?? null,
            amprenta_jeton(jeton_invitat(true)),
            $amprentaFis,
        ]);
    } catch (Throwable $e) {
        @unlink($destinatie);
        @unlink(THUMB_DIR . $numeFisier . '.jpg');
        raspunde(['ok' => false, 'eroare' => 'Eroare la salvarea în baza de date.'], 500);
    }

    raspunde([
        'ok'       => true,
        'reusite'  => 1,
        'moderare' => moderare_activa(),
    ]);
}

raspunde(['ok' => false, 'eroare' => 'Acțiune necunoscută.'], 400);
