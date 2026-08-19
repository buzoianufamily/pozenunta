<?php
require_once __DIR__ . '/functions.php';
header('Content-Type: application/json; charset=utf-8');
asigura_schema();
inchide_sesiune();   // încărcarea nu atinge sesiunea

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'eroare' => 'Metodă invalidă.']);
    exit;
}

$nume  = trim((string)($_POST['nume']  ?? ''));
$mesaj = trim((string)($_POST['mesaj'] ?? ''));
// limitări de bun-simț
$nume  = mb_substr($nume,  0, 120);
$mesaj = mb_substr($mesaj, 0, 1000);

/* Normalizează $_FILES într-o listă uniformă, indiferent dacă
   s-a trimis un singur fișier sau mai multe. */
function colecteaza_fisiere(): array {
    $rezultat = [];
    foreach (['fisier', 'fisiere'] as $camp) {
        if (!isset($_FILES[$camp])) continue;
        $f = $_FILES[$camp];
        if (is_array($f['name'])) {
            $n = count($f['name']);
            for ($i = 0; $i < $n; $i++) {
                $rezultat[] = [
                    'name'     => $f['name'][$i],
                    'type'     => $f['type'][$i],
                    'tmp_name' => $f['tmp_name'][$i],
                    'error'    => $f['error'][$i],
                    'size'     => $f['size'][$i],
                ];
            }
        } else {
            $rezultat[] = $f;
        }
    }
    return $rezultat;
}

$fisiere = colecteaza_fisiere();
if (empty($fisiere)) {
    echo json_encode(['ok' => false, 'eroare' => 'Niciun fișier primit.']);
    exit;
}

$ip          = $_SERVER['REMOTE_ADDR'] ?? null;
$aprobat     = moderare_activa() ? 0 : 1;
/* Codul secret al invitatului, ca să-și poată șterge singur fișierele. */
$amprenta    = amprenta_jeton(jeton_invitat(true));
$reusite     = 0;
$duplicate   = 0;
$erori       = [];
$ext_imagini = extensii_imagini();
$ext_video   = extensii_video();
$ext_permise = extensii_permise();

$stmt = db()->prepare(
    'INSERT INTO poze (nume_fisier, nume_original, nume_invitat, mesaj, tip, marime, aprobat, ip, jeton, amprenta_fisier)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

foreach ($fisiere as $f) {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
            $erori[] = h($f['name']) . ': fișier prea mare pentru server.';
        }
        continue;
    }
    if ($f['size'] <= 0 || $f['size'] > MAX_FILE_SIZE) {
        $erori[] = h($f['name']) . ': dimensiune neacceptată.';
        continue;
    }
    if (!is_uploaded_file($f['tmp_name'])) {
        continue;
    }

    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $ext_permise, true)) {
        $erori[] = h($f['name']) . ': tip de fișier neacceptat.';
        continue;
    }
    $tip = in_array($ext, $ext_video, true) ? 'video' : 'imagine';

    /* Același fișier există deja? Îl considerăm rezolvat, nu eroare:
       invitatul nu a greșit cu nimic, poza lui e deja în album. */
    $amprentaFis = amprenta_fisier($f['tmp_name']);
    if (duplicat_existent($amprentaFis) !== null) {
        $duplicate++;
        continue;
    }

    // nume unic, imposibil de ghicit
    try {
        $numeFisier = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    } catch (Throwable $e) {
        $numeFisier = date('Ymd') . '_' . uniqid('', true) . '.' . $ext;
    }
    $destinatie = UPLOAD_DIR . $numeFisier;

    if (!move_uploaded_file($f['tmp_name'], $destinatie)) {
        $erori[] = h($f['name']) . ': nu s-a putut salva pe server.';
        continue;
    }
    @chmod($destinatie, 0644);

    /* HEIC nevăzut de Android: îl transformăm noi, dacă telefonul n-a putut. */
    $convertit = converteste_heic($destinatie);
    if ($convertit !== null) {
        $destinatie = $convertit;
        $numeFisier = basename($convertit);
    }

    // miniatură: imaginile din original, filmele din posterul trimis de telefon
    $caleMiniatura = THUMB_DIR . $numeFisier . '.jpg';
    if ($tip === 'imagine') {
        @creeaza_thumbnail($destinatie, $caleMiniatura);
    } elseif (!empty($_FILES['poster']) && (($_FILES['poster']['error'] ?? 1) === UPLOAD_ERR_OK) && is_uploaded_file($_FILES['poster']['tmp_name'])) {
        @creeaza_thumbnail($_FILES['poster']['tmp_name'], $caleMiniatura);
    }
    /* Fără cadru de la telefon, încercăm să-l scoatem pe server. */
    if ($tip === 'video' && !is_file($caleMiniatura)) {
        @thumbnail_din_film($destinatie, $caleMiniatura);
    }

    try {
        $stmt->execute([
            $numeFisier,
            mb_substr((string)$f['name'], 0, 255),
            $nume !== '' ? $nume : null,
            $mesaj !== '' ? $mesaj : null,
            $tip,
            (int)$f['size'],
            $aprobat,
            $ip,
            $amprenta,
            $amprentaFis,
        ]);
        $reusite++;
    } catch (Throwable $e) {
        @unlink($destinatie);
        @unlink(THUMB_DIR . $numeFisier . '.jpg');
        $erori[] = h($f['name']) . ': eroare la salvarea în baza de date.';
    }
}

echo json_encode([
    'ok'        => $reusite > 0 || $duplicate > 0,
    'reusite'   => $reusite,
    'duplicate' => $duplicate,
    'duplicat'  => $reusite === 0 && $duplicate > 0,
    'erori'     => $erori,
    'moderare'  => $aprobat === 0,
], JSON_UNESCAPED_UNICODE);
