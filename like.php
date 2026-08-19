<?php
/* ============================================================
   APRECIERI (inimioara)
   ------------------------------------------------------------
   Se numără pe server, câte una per invitat și poză. Înainte,
   numărul creștea la fiecare apăsare, iar ce împiedica dublarea
   era doar memoria telefonului — deci putea fi umflat ușor.

   Invitatul e recunoscut după același cookie ca la încărcare.
   Cine nu are cookie primește unul acum: ca să apreciezi nu
   trebuie să fi încărcat ceva înainte.
   ============================================================ */
require_once __DIR__ . '/functions.php';
header('Content-Type: application/json; charset=utf-8');
asigura_schema();
inchide_sesiune();   // nu scriem în sesiune: eliberăm blocajul

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$id  = (int)($_POST['id'] ?? 0);
$val = (int)($_POST['val'] ?? 1);
$val = $val < 0 ? -1 : 1;   // +1 apreciază, -1 retrage aprecierea

if ($id <= 0) { echo json_encode(['ok' => false]); exit; }

$amprenta = amprenta_jeton(jeton_invitat(true));
if ($amprenta === null) { echo json_encode(['ok' => false]); exit; }

try {
    /* Poza trebuie să existe și să fie vizibilă public. */
    $st = db()->prepare('SELECT id FROM poze WHERE id = ? AND aprobat = 1');
    $st->execute([$id]);
    if (!$st->fetchColumn()) { echo json_encode(['ok' => false]); exit; }

    if ($val === 1) {
        /* „IGNORE": dacă a apreciat deja, nu se întâmplă nimic — exact
           ce vrem, fără să tratăm separat eroarea de cheie dublată. */
        $st = db()->prepare('INSERT IGNORE INTO aprecieri (poza_id, jeton) VALUES (?, ?)');
        $st->execute([$id, $amprenta]);
    } else {
        $st = db()->prepare('DELETE FROM aprecieri WHERE poza_id = ? AND jeton = ?');
        $st->execute([$id, $amprenta]);
    }

    /* Numărul adevărat, recalculat din tabel. Îl păstrăm și în coloana
       veche „aprecieri", ca sortarea „cele mai apreciate" să rămână
       rapidă (are index) fără să numere la fiecare afișare. */
    $st = db()->prepare('SELECT COUNT(*) FROM aprecieri WHERE poza_id = ?');
    $st->execute([$id]);
    $nou = (int)$st->fetchColumn();

    $st = db()->prepare('UPDATE poze SET aprecieri = ? WHERE id = ?');
    $st->execute([$nou, $id]);

    echo json_encode(['ok' => true, 'aprecieri' => $nou, 'apreciat' => $val === 1]);
} catch (Throwable $e) {
    /* Un „ok:false" mut lăsa pagina să arate inima apăsată deși pe server
       nu se salvase nimic. Spunem ce s-a stricat, ca să se vadă. */
    $lipsaTabela = stripos($e->getMessage(), 'aprecieri') !== false
                   && (stripos($e->getMessage(), "doesn't exist") !== false
                       || stripos($e->getMessage(), 'not exist') !== false);
    http_response_code(500);
    echo json_encode([
        'ok'     => false,
        'eroare' => $lipsaTabela
            /* Nu mai trimitem la instalare.php: a fost scos de pe server
               anume, iar un invitat n-are ce face cu numele unui fișier.
               Îi spunem doar că nu e din vina lui și pe cine să anunțe. */
            ? 'Aprecierile nu funcționează acum. Spune-i organizatorului — nu e ceva ce ai greșit tu.'
            : 'Aprecierea nu s-a putut salva. Încearcă din nou.',
    ], JSON_UNESCAPED_UNICODE);
}
