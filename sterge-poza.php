<?php
/* ============================================================
   ȘTERGEREA PROPRIULUI FIȘIER DE CĂTRE INVITAT
   ------------------------------------------------------------
   Invitatul nu are cont. Dovada că fișierul e al lui e cookie-ul
   secret primit la încărcare (vezi jeton_invitat în functions.php).
   Comparăm amprenta din cookie cu cea salvată lângă fișier.

   Un invitat poate șterge DOAR ce a încărcat el. Mirii, autentificați
   ca administratori, pot șterge orice (din panou).
   ============================================================ */
require_once __DIR__ . '/functions.php';
header('Content-Type: application/json; charset=utf-8');
inchide_sesiune();   // starea de admin s-a citit deja

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'eroare' => 'Metodă invalidă.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['ok' => false, 'eroare' => 'Fișier neindicat.']);
    exit;
}

/* Fără cookie nu există dovadă de proprietate. Nu creăm unul acum:
   ar fi inutil, pentru că nu s-ar potrivi cu nimic. */
$amprenta = amprenta_jeton(jeton_invitat(false));
$admin    = este_admin();

if ($amprenta === null && !$admin) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'eroare' => 'Poți șterge doar fișierele încărcate de pe acest telefon.']);
    exit;
}

try {
    $st = db()->prepare('SELECT id, nume_fisier, jeton FROM poze WHERE id = ?');
    $st->execute([$id]);
    $poza = $st->fetch();

    if (!$poza) {
        echo json_encode(['ok' => false, 'eroare' => 'Fișierul nu mai există.']);
        exit;
    }

    /* Verificarea propriu-zisă. hash_equals compară în timp constant,
       ca să nu se poată ghici amprenta măsurând durata răspunsului. */
    $eAlMeu = !empty($poza['jeton']) && $amprenta !== null
              && hash_equals((string)$poza['jeton'], $amprenta);

    if (!$eAlMeu && !$admin) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'eroare' => 'Acest fișier a fost încărcat de altcineva.']);
        exit;
    }

    /* Întâi rândul din baza de date, apoi fișierele de pe disc:
       dacă ștergerea din baza de date eșuează, nu rămânem cu poza
       dispărută din album dar prezentă în listă. */
    $del = db()->prepare('DELETE FROM poze WHERE id = ?');
    $del->execute([$id]);

    @unlink(UPLOAD_DIR . $poza['nume_fisier']);
    @unlink(THUMB_DIR . $poza['nume_fisier'] . '.jpg');

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'eroare' => 'Nu s-a putut șterge. Încearcă din nou.']);
}
