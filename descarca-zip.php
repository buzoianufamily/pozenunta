<?php
/* ============================================================
   DESCĂRCAREA ALBUMULUI CA ARHIVĂ
   ------------------------------------------------------------
   Trei lucruri contează la un album mare:

   1. Arhiva se construia în /tmp, care pe cPanel e adesea sub 1 GB.
      Acum se scrie pe discul contului, unde e spațiul plătit.
   2. Se comprimau fișiere JPEG și MP4 — deja comprimate. Consuma
      procesor mult, fără să scadă dimensiunea. Acum se doar
      împachetează (store), ceea ce e mult mai rapid.
   3. Un album de zeci de GB nu încape într-o singură descărcare.
      Se poate lua pe tranșe, iar pagina arată din câte e nevoie.
   ============================================================ */
require_once __DIR__ . '/functions.php';
cere_admin();

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    die('Extensia ZIP nu este disponibilă pe server. Descarcă fișierele prin File Manager / FTP din folderul „uploads".');
}

/* Cât punem, cel mult, într-o singură arhivă. */
define('ZIP_MAX_OCTETI', 3 * 1024 * 1024 * 1024);   // 3 GB
define('ZIP_MAX_FISIERE', 3000);

@set_time_limit(0);
@ini_set('memory_limit', '512M');

$randuri = db()->query(
    'SELECT nume_fisier, nume_invitat, marime, data_incarcare
     FROM poze ORDER BY data_incarcare ASC, id ASC'
)->fetchAll();
if (empty($randuri)) { die('Nu există fotografii de descărcat.'); }

/* ------------------------------------------------------------
   Împărțim albumul în tranșe care încap într-o arhivă.
   ------------------------------------------------------------ */
$transe = [];
$curent = ['de' => 0, 'pana' => -1, 'octeti' => 0, 'fisiere' => 0];
foreach ($randuri as $i => $r) {
    $m = (int)$r['marime'];
    if ($curent['fisiere'] > 0 &&
        ($curent['octeti'] + $m > ZIP_MAX_OCTETI || $curent['fisiere'] >= ZIP_MAX_FISIERE)) {
        $transe[] = $curent;
        $curent = ['de' => $i, 'pana' => -1, 'octeti' => 0, 'fisiere' => 0];
    }
    $curent['pana'] = $i;
    $curent['octeti'] += $m;
    $curent['fisiere']++;
}
if ($curent['fisiere'] > 0) $transe[] = $curent;

$nrTranse = count($transe);
$transa    = isset($_GET['transa']) ? (int)$_GET['transa'] : 0;

/* ------------------------------------------------------------
   Fără tranșă cerută: arătăm o pagină cu ce e de descărcat.
   ------------------------------------------------------------ */
if ($transa < 1 || $transa > $nrTranse) {
    $totalOcteti = array_sum(array_column($transe, 'octeti'));
    ?><!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="icon" href="assets/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
<title>Descarcă albumul · <?= h(NUME_MIRE) ?> &amp; <?= h(NUME_MIREASA) ?></title>
<link rel="stylesheet" href="assets/fonturi.css">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="container narrow" style="padding:40px 0 60px">
  <div class="panou">
    <h2>Descarcă albumul</h2>
    <p class="ajutor">
      <?= count($randuri) ?> fișiere · <?= h(format_marime($totalOcteti)) ?> în total
    </p>

    <?php if ($nrTranse === 1): ?>
      <p>Albumul încape într-o singură arhivă.</p>
      <p style="margin-top:18px">
        <a class="btn btn-primar" href="?transa=1">Descarcă arhiva</a>
      </p>
    <?php else: ?>
      <p>
        Albumul e prea mare pentru o singură arhivă, așa că l-am împărțit în
        <strong><?= $nrTranse ?> părți</strong>. Descarcă-le pe rând — fiecare
        e o arhivă de sine stătătoare.
      </p>
      <div style="display:flex;flex-direction:column;gap:10px;margin-top:18px">
        <?php foreach ($transe as $k => $t): ?>
          <a class="btn btn-ghost" href="?transa=<?= $k + 1 ?>" style="justify-content:space-between;display:flex">
            <span>Partea <?= $k + 1 ?> din <?= $nrTranse ?></span>
            <span style="color:var(--muted);font-size:.85rem">
              <?= $t['fisiere'] ?> fișiere · <?= h(format_marime($t['octeti'])) ?>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <p class="ajutor" style="margin-top:22px">
      Dacă albumul e foarte mare, cel mai comod rămâne copierea folderului
      <code>uploads</code> prin FTP sau din File Manager.
    </p>
    <p style="margin-top:18px"><a class="btn btn-ghost btn-mic" href="admin.php">← Înapoi în panou</a></p>
  </div>
</main>
</body>
</html>
    <?php
    exit;
}

/* ------------------------------------------------------------
   Construim arhiva cerută.
   ------------------------------------------------------------ */
$t     = $transe[$transa - 1];
$felie = array_slice($randuri, $t['de'], $t['pana'] - $t['de'] + 1);

/* Fișierul temporar stă pe discul contului, nu în /tmp: acolo e spațiul. */
$dirTmp = UPLOAD_DIR . '.arhive/';
if (!is_dir($dirTmp) && !@mkdir($dirTmp, 0755, true) && !is_dir($dirTmp)) {
    http_response_code(500);
    die('Nu s-a putut pregăti folderul pentru arhivă.');
}
if (!is_file($dirTmp . '.htaccess')) {
    @file_put_contents($dirTmp . '.htaccess', "Require all denied\n");
}

/* Arhivele rămase de la încercări întrerupte se curăță. */
foreach ((array)@glob($dirTmp . '*.zip') as $vechi) {
    if (@filemtime($vechi) < time() - 3600) @unlink($vechi);
}

$liber = @disk_free_space($dirTmp);
if ($liber !== false && $liber < $t['octeti'] * 1.05) {
    http_response_code(507);
    die('Nu e destul spațiu liber pe server pentru arhivă (nevoie de ~'
        . h(format_marime((int)$t['octeti'])) . ', liber '
        . h(format_marime((int)$liber)) . '). Descarcă folderul „uploads" prin FTP.');
}

$caleTmp = $dirTmp . uniqid('album_', true) . '.zip';
$zip = new ZipArchive();
if ($zip->open($caleTmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    die('Nu s-a putut crea arhiva.');
}

$folosite = [];
foreach ($felie as $r) {
    $cale = UPLOAD_DIR . $r['nume_fisier'];
    if (!is_file($cale)) continue;

    // nume prietenos în arhivă: data + numele invitatului
    $ext      = pathinfo($r['nume_fisier'], PATHINFO_EXTENSION);
    /* Scoatem ce n-are ce căuta într-un nume de fișier (emoji, semne),
       apoi strângem spațiile rămase. Fără asta, „Colegii de la birou 🤍"
       devenea un nume terminat cu spațiu — iar Windows nu dezarhivează
       curat asemenea nume. */
    $eticheta = $r['nume_invitat'] ? preg_replace('/[^\p{L}\p{N} _-]/u', '', $r['nume_invitat']) : '';
    $eticheta = trim(preg_replace('/\s+/u', ' ', (string)$eticheta));
    if ($eticheta === '') $eticheta = 'invitat';
    $baza     = date('Ymd_His', strtotime($r['data_incarcare'])) . '_' . $eticheta;
    $numeInZip = $baza . '.' . $ext;
    $i = 1;
    while (isset($folosite[$numeInZip])) { $numeInZip = $baza . '_' . (++$i) . '.' . $ext; }
    $folosite[$numeInZip] = true;

    $zip->addFile($cale, $numeInZip);
    /* Fără compresie: pozele și filmele sunt deja comprimate, iar
       comprimarea lor din nou doar arde procesor degeaba. */
    if (method_exists($zip, 'setCompressionName')) {
        @$zip->setCompressionName($numeInZip, ZipArchive::CM_STORE);
    }
}
if (!$zip->close()) {
    @unlink($caleTmp);
    http_response_code(500);
    die('Arhiva nu s-a putut finaliza. Probabil s-a terminat spațiul pe disc.');
}

$sufix   = $nrTranse > 1 ? '_partea' . $transa . 'din' . $nrTranse : '';
/* Numele arhivei trebuie să meargă pe orice sistem, deci fără diacritice —
   dar înlocuite, nu șterse: „Răzvan" devenea „Rzvan" pe fișierul pe care
   tocmai voi îl păstrați toată viața. */
function fara_diacritice(string $t): string {
    $harta = ['ă'=>'a','â'=>'a','î'=>'i','ș'=>'s','ş'=>'s','ț'=>'t','ţ'=>'t',
              'Ă'=>'A','Â'=>'A','Î'=>'I','Ș'=>'S','Ş'=>'S','Ț'=>'T','Ţ'=>'T'];
    return preg_replace('/[^A-Za-z0-9]/', '', strtr($t, $harta));
}

$numeZip = 'Album_' . fara_diacritice(NUME_MIRE)
         . '_' . fara_diacritice(NUME_MIREASA)
         . $sufix . '_' . date('Ymd') . '.zip';

/* Oprim orice tampon: fișierul poate fi de ordinul gigaocteților. */
while (ob_get_level() > 0) { ob_end_clean(); }

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $numeZip . '"');
header('Content-Length: ' . filesize($caleTmp));
header('Cache-Control: no-store');
header('X-Accel-Buffering: no');

$fp = fopen($caleTmp, 'rb');
if ($fp) {
    while (!feof($fp)) {
        echo fread($fp, 1024 * 512);
        flush();
        if (connection_aborted()) break;   // a renunțat: nu mai trimitem degeaba
    }
    fclose($fp);
}
@unlink($caleTmp);
exit;
