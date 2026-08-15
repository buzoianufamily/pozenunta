<?php
/* ============================================================
   DIAGNOSTIC SERVER — ce poate găzduirea și ce trebuie reglat
   ------------------------------------------------------------
   Pagina e vizibilă DOAR după autentificare ca admin, pentru că
   arată detalii interne despre server.

   Deschide:  /info.php          → raportul pe scurt
              /info.php?full=1   → în plus, tot phpinfo()

   ȘTERGE fișierul după nuntă (sau lasă-l, e protejat de login).
   ============================================================ */
require_once __DIR__ . '/functions.php';
cere_admin();

/* ---------- ajutoare ---------- */

/** Transformă „256M", „1G", „512K" în octeți. Întoarce -1 pentru nelimitat. */
function ini_octeti(string $val): int {
    $val = trim($val);
    if ($val === '') return 0;
    if ($val === '-1') return -1;
    $ultima = strtolower($val[strlen($val) - 1]);
    $nr = (float)$val;
    switch ($ultima) {
        case 'g': $nr *= 1024; // fall through
        case 'm': $nr *= 1024; // fall through
        case 'k': $nr *= 1024;
    }
    return (int)$nr;
}

function om(int $octeti): string {
    if ($octeti < 0) return 'nelimitat';
    return format_marime($octeti);
}

/* Colectăm rândurile raportului: [secțiune][] = [etichetă, valoare, stare, notă] */
$raport = [];
function rand_raport(string $sectiune, string $eticheta, string $valoare, string $stare = 'info', string $nota = ''): void {
    global $raport;
    $raport[$sectiune][] = ['eticheta' => $eticheta, 'valoare' => $valoare, 'stare' => $stare, 'nota' => $nota];
}

/* Probleme și recomandări adunate pe parcurs */
$probleme = [];
function problema(string $text): void { global $probleme; $probleme[] = $text; }

/* ============================================================
   1. SERVER ȘI PHP
   ============================================================ */
$phpVer   = PHP_VERSION;
$phpMajor = PHP_MAJOR_VERSION * 10 + PHP_MINOR_VERSION;
rand_raport('Server și PHP', 'Versiune PHP', $phpVer,
    $phpMajor >= 81 ? 'ok' : ($phpMajor >= 74 ? 'atentie' : 'rau'),
    $phpMajor >= 81 ? 'Bună.' : 'Recomandat PHP 8.1+ (din cPanel → MultiPHP Manager).');
if ($phpMajor < 81) problema('Treci pe PHP 8.1 sau mai nou din cPanel → MultiPHP Manager (mai rapid și încă are suport de securitate).');

$sapi = PHP_SAPI;
rand_raport('Server și PHP', 'Interfață PHP (SAPI)', $sapi,
    (stripos($sapi, 'lsapi') !== false || stripos($sapi, 'fpm') !== false) ? 'ok' : 'atentie',
    (stripos($sapi, 'lsapi') !== false) ? 'LiteSpeed — ideal pentru încărcări multe în paralel.' : '');

rand_raport('Server și PHP', 'Software server', (string)($_SERVER['SERVER_SOFTWARE'] ?? 'necunoscut'));
rand_raport('Server și PHP', 'Sistem', php_uname('s') . ' ' . php_uname('r') . ' (' . php_uname('m') . ')');
rand_raport('Server și PHP', 'Rădăcina site-ului', (string)($_SERVER['DOCUMENT_ROOT'] ?? __DIR__));
rand_raport('Server și PHP', 'Fus orar PHP', date_default_timezone_get() . ' · ora serverului: ' . date('d.m.Y H:i:s'));
rand_raport('Server și PHP', 'Adresa configurată (SITE_URL)', SITE_URL,
    (stripos(SITE_URL, (string)($_SERVER['HTTP_HOST'] ?? '')) !== false) ? 'ok' : 'atentie',
    (stripos(SITE_URL, (string)($_SERVER['HTTP_HOST'] ?? '')) !== false)
        ? 'Se potrivește cu domeniul pe care ești acum.'
        : 'NU se potrivește cu domeniul curent (' . h((string)($_SERVER['HTTP_HOST'] ?? '?')) . '). Codul QR și previzualizarea pe WhatsApp vor arăta adresa din SITE_URL!');
if (stripos(SITE_URL, (string)($_SERVER['HTTP_HOST'] ?? '')) === false) {
    problema('SITE_URL din config.php („' . SITE_URL . '") nu e domeniul pe care rulează site-ul acum („' . (string)($_SERVER['HTTP_HOST'] ?? '?') . '"). Corectează-l, altfel codul QR trimite invitații în altă parte.');
}

/* ============================================================
   2. LIMITE DE ÎNCĂRCARE  ← partea cea mai importantă
   ============================================================ */
$umf   = ini_octeti((string)ini_get('upload_max_filesize'));
$pms   = ini_octeti((string)ini_get('post_max_size'));
$mem   = ini_octeti((string)ini_get('memory_limit'));
$maxEx = (int)ini_get('max_execution_time');
$maxIn = (int)ini_get('max_input_time');
$maxFi = (int)ini_get('max_file_uploads');

/* Limita reală pe fișier = cea mai mică dintre server și aplicație */
$limitaServer = ($pms > 0 && $pms < $umf) ? $pms : $umf;
$limitaReala  = min($limitaServer > 0 ? $limitaServer : PHP_INT_MAX, MAX_FILE_SIZE);

rand_raport('Limite de încărcare', 'upload_max_filesize', (string)ini_get('upload_max_filesize') . ' (' . om($umf) . ')',
    $umf >= 1073741824 ? 'ok' : ($umf >= 268435456 ? 'atentie' : 'rau'),
    $umf >= 1073741824 ? 'Filme de până la 1 GB trec.' : 'Mic pentru filme de la telefon.');

rand_raport('Limite de încărcare', 'post_max_size', (string)ini_get('post_max_size') . ' (' . om($pms) . ')',
    ($pms >= $umf && $pms > 0) ? 'ok' : 'rau',
    ($pms >= $umf) ? 'Corect: mai mare sau egal cu upload_max_filesize.' : 'PROBLEMĂ: mai mic decât upload_max_filesize — el devine limita reală.');

rand_raport('Limite de încărcare', 'memory_limit', (string)ini_get('memory_limit') . ' (' . om($mem) . ')',
    ($mem < 0 || $mem >= 268435456) ? 'ok' : ($mem >= 134217728 ? 'atentie' : 'rau'),
    'Contează la facerea miniaturilor pentru poze mari.');

rand_raport('Limite de încărcare', 'max_execution_time', $maxEx === 0 ? 'nelimitat' : $maxEx . ' s',
    ($maxEx === 0 || $maxEx >= 300) ? 'ok' : ($maxEx >= 120 ? 'atentie' : 'rau'),
    'Cât poate dura procesarea unui fișier încărcat.');

rand_raport('Limite de încărcare', 'max_input_time', $maxIn < 0 ? 'nelimitat' : $maxIn . ' s',
    ($maxIn < 0 || $maxIn >= 300) ? 'ok' : ($maxIn >= 120 ? 'atentie' : 'rau'),
    'Cât poate dura PRIMIREA fișierului (urcare lentă pe 4G).');

rand_raport('Limite de încărcare', 'max_file_uploads', (string)$maxFi,
    $maxFi >= 20 ? 'ok' : 'atentie', 'Câte fișiere pot merge într-o singură cerere.');

rand_raport('Limite de încărcare', 'MAX_FILE_SIZE (config.php)', om(MAX_FILE_SIZE), 'info', 'Limita pusă de aplicație.');

rand_raport('Limite de încărcare', '➜ LIMITA REALĂ pe fișier', om((int)$limitaReala),
    $limitaReala >= 536870912 ? 'ok' : ($limitaReala >= 134217728 ? 'atentie' : 'rau'),
    'Cea mai mică dintre limitele de mai sus — asta simte invitatul.');

rand_raport('Limite de încărcare', 'Limită per invitat', 'NU există',
    'ok', 'Fiecare invitat poate încărca oricâte fișiere. Singura limită e spațiul pe disc.');

if ($pms > 0 && $pms < $umf) {
    problema('post_max_size (' . om($pms) . ') e mai mic decât upload_max_filesize (' . om($umf) . ') — limita reală scade la ' . om($pms) . '. Fă-le egale.');
}
if ($limitaServer > 0 && MAX_FILE_SIZE > $limitaServer) {
    problema('config.php promite ' . om(MAX_FILE_SIZE) . ' pe fișier, dar serverul acceptă doar ' . om($limitaServer) . '. Filmele mai mari eșuează. Ori mărești limitele serverului, ori cobori MAX_FILE_SIZE ca să fie sincer.');
}
if ($maxIn >= 0 && $maxIn < 300) {
    problema('max_input_time = ' . $maxIn . 's e mic. Un film de 500 MB pe 4G lent poate depăși timpul și se rupe încărcarea. Recomand 600 sau -1.');
}
if ($mem >= 0 && $mem < 268435456) {
    problema('memory_limit = ' . om($mem) . '. La poze foarte mari (peste 20 MP) facerea miniaturii poate pica. Recomand 256M.');
}

/* ============================================================
   3. PRELUCRAREA IMAGINILOR
   ============================================================ */
$areGd = extension_loaded('gd');
rand_raport('Imagini', 'Extensia GD', $areGd ? 'instalată' : 'LIPSEȘTE',
    $areGd ? 'ok' : 'rau', $areGd ? '' : 'Fără GD nu se fac miniaturi — galeria va încărca pozele mari, foarte lent!');
if (!$areGd) problema('Extensia GD lipsește — nu se pot genera miniaturi. Activeaz-o din cPanel → Select PHP Version → Extensions.');

if ($areGd) {
    $gd = gd_info();
    $formate = [];
    foreach (['JPEG' => 'JPEG Support', 'PNG' => 'PNG Support', 'GIF' => 'GIF Create Support', 'WebP' => 'WebP Support', 'AVIF' => 'AVIF Support'] as $nume => $cheie) {
        if (!empty($gd[$cheie])) $formate[] = $nume;
    }
    rand_raport('Imagini', 'Versiune GD', (string)($gd['GD Version'] ?? '?'));
    rand_raport('Imagini', 'Formate suportate', $formate ? implode(', ', $formate) : 'niciunul',
        in_array('JPEG', $formate, true) ? 'ok' : 'rau');
}

$areExif = function_exists('exif_read_data');
rand_raport('Imagini', 'Extensia EXIF', $areExif ? 'instalată' : 'lipsește',
    $areExif ? 'ok' : 'atentie',
    $areExif ? 'Pozele de pe telefon se rotesc corect.' : 'Fără ea, unele poze apar culcate în galerie.');
if (!$areExif) problema('Extensia EXIF lipsește — pozele făcute pe verticală pot apărea rotite. Activeaz-o din cPanel → Select PHP Version → Extensions.');

$areImagick = extension_loaded('imagick');
rand_raport('Imagini', 'Imagick', $areImagick ? 'instalată' : 'lipsește', 'info',
    $areImagick ? 'Bonus: poate deschide HEIC (iPhone) direct pe server.' : 'Nu e obligatorie — telefonul convertește HEIC înainte de trimitere.');

rand_raport('Imagini', 'Lățime miniatură (config)', THUMB_WIDTH . ' px', 'info', 'Mai mic = galerie mai rapidă.');

/* ============================================================
   4. BAZA DE DATE
   ============================================================ */
/* Conexiune proprie, NU db(): funcția din config.php face die() la eroare,
   iar pagina de diagnostic trebuie să funcționeze chiar și cu baza căzută. */
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 5]
    );
    rand_raport('Baza de date', 'Conexiune', 'reușită', 'ok');
    rand_raport('Baza de date', 'Versiune', (string)$pdo->getAttribute(PDO::ATTR_SERVER_VERSION), 'ok');
    rand_raport('Baza de date', 'Bază / user', DB_NAME . ' / ' . DB_USER);

    foreach (['max_connections' => 'Conexiuni maxime', 'max_allowed_packet' => 'Pachet maxim'] as $vr => $et) {
        try {
            $s = $pdo->query("SHOW VARIABLES LIKE '$vr'")->fetch();
            if ($s) {
                $val = $vr === 'max_allowed_packet' ? om((int)$s['Value']) : $s['Value'];
                $stare = ($vr === 'max_connections' && (int)$s['Value'] < 50) ? 'atentie' : 'ok';
                rand_raport('Baza de date', $et, $val, $stare,
                    $vr === 'max_connections' ? 'Pentru 100 de invitați deodată, 50+ e confortabil.' : '');
                if ($vr === 'max_connections' && (int)$s['Value'] < 50) {
                    problema('max_connections în MySQL e ' . $s['Value'] . ' — cam mic dacă 100 de oameni încarcă simultan. Cere-i gazdei să-l urce la 100+.');
                }
            }
        } catch (Throwable $e) { /* unele găzduiri nu permit SHOW VARIABLES */ }
    }
    try {
        $s = $pdo->query("SHOW STATUS LIKE 'Threads_connected'")->fetch();
        if ($s) rand_raport('Baza de date', 'Conexiuni active acum', $s['Value']);
    } catch (Throwable $e) {}

    foreach (['poze' => 'Poze/filme în baza de date', 'urari' => 'Urări', 'setari' => 'Setări'] as $t => $et) {
        try {
            $n = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            rand_raport('Baza de date', $et, (string)$n, 'ok');
        } catch (Throwable $e) {
            rand_raport('Baza de date', $et, 'tabela lipsește', 'rau', 'Rulează setup.php o dată.');
            problema('Tabela „' . $t . '" lipsește din baza de date. Deschide setup.php o singură dată ca să se creeze.');
        }
    }
} catch (Throwable $e) {
    rand_raport('Baza de date', 'Conexiune', 'EȘUATĂ', 'rau', 'Verifică DB_NAME / DB_USER / DB_PASS din config.php.');
    problema('Nu se poate conecta la baza de date. Verifică datele din config.php.');
}

/* ============================================================
   5. SPAȚIU PE DISC
   ============================================================ */
$liber = @disk_free_space(__DIR__);
$total = @disk_total_space(__DIR__);
$cota  = DISK_QUOTA_GB * 1024 * 1024 * 1024;

/* Cât ocupă deja folderul uploads */
$dimUploads = 0; $nrFisiere = 0;
try {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(UPLOAD_DIR, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($it as $f) {
        if ($f->isFile()) { $dimUploads += $f->getSize(); $nrFisiere++; }
    }
} catch (Throwable $e) { $nrFisiere = -1; }

if ($liber !== false && $total !== false && $total > 0) {
    $folosit = $total - $liber;
    $proc = round($folosit / $total * 100, 1);
    rand_raport('Spațiu pe disc', 'Total (raportat de server)', om((int)$total));
    rand_raport('Spațiu pe disc', 'Folosit', om((int)$folosit) . ' (' . $proc . '%)',
        $proc < 75 ? 'ok' : ($proc < 90 ? 'atentie' : 'rau'));
    rand_raport('Spațiu pe disc', 'Liber', om((int)$liber),
        $liber > 5368709120 ? 'ok' : ($liber > 1073741824 ? 'atentie' : 'rau'));
} else {
    rand_raport('Spațiu pe disc', 'Citire spațiu', 'indisponibilă', 'atentie', 'Găzduirea nu permite disk_free_space(). Vezi cPanel → Disk Usage.');
}
rand_raport('Spațiu pe disc', 'DISK_QUOTA_GB (config)', DISK_QUOTA_GB . ' GB', 'info', 'Doar pentru bara din panou. Mărește-l când cumperi spațiu.');
rand_raport('Spațiu pe disc', 'Ocupat de uploads/', $nrFisiere >= 0 ? om($dimUploads) . ' · ' . $nrFisiere . ' fișiere' : 'nu s-a putut citi');

/* Estimare: câte poze/filme mai încap */
if ($liber !== false) {
    $estPoze  = (int)floor($liber / (2 * 1024 * 1024));    // ~2 MB/poză după micșorare
    $estFilme = (int)floor($liber / (60 * 1024 * 1024));   // ~60 MB/film scurt
    rand_raport('Spațiu pe disc', '➜ Mai încap aproximativ',
        number_format($estPoze, 0, ',', '.') . ' poze SAU ' . number_format($estFilme, 0, ',', '.') . ' filme scurte',
        'info', 'Estimare: ~2 MB/poză (după micșorarea din telefon), ~60 MB/film de un minut.');
}

/* ============================================================
   6. PERFORMANȚĂ ȘI RESURSE
   ============================================================ */
$op = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
if (is_array($op)) {
    $activ = !empty($op['opcache_enabled']);
    $nota = '';
    if ($activ && !empty($op['opcache_statistics'])) {
        $st = $op['opcache_statistics'];
        $hits = (float)($st['hits'] ?? 0); $miss = (float)($st['misses'] ?? 0);
        if ($hits + $miss > 0) $nota = 'Eficiență: ' . round($hits / ($hits + $miss) * 100, 1) . '%';
    }
    rand_raport('Performanță', 'OPcache', $activ ? 'pornit' : 'oprit', $activ ? 'ok' : 'atentie', $nota ?: 'Ține codul PHP compilat în memorie — pagini mai rapide.');
    if (!$activ) problema('OPcache e oprit. Pornește-l din cPanel → Select PHP Version → Extensions (bifează opcache) — site-ul devine vizibil mai rapid.');
} else {
    rand_raport('Performanță', 'OPcache', 'indisponibil', 'atentie');
}

$nuclee = 0;
if (is_readable('/proc/cpuinfo')) {
    $cpu = @file_get_contents('/proc/cpuinfo');
    if ($cpu !== false) $nuclee = substr_count($cpu, 'processor');
}
rand_raport('Performanță', 'Nuclee procesor (vizibile)', $nuclee > 0 ? (string)$nuclee : 'necunoscut',
    $nuclee >= 2 ? 'ok' : 'info');

if (function_exists('sys_getloadavg')) {
    $l = sys_getloadavg();
    if (is_array($l)) {
        $incarcare = round($l[0], 2);
        $prag = $nuclee > 0 ? $nuclee : 4;
        rand_raport('Performanță', 'Încărcare server (1 min)', (string)$incarcare,
            $incarcare < $prag ? 'ok' : ($incarcare < $prag * 2 ? 'atentie' : 'rau'),
            'Sub ' . $prag . ' = relaxat. E un server partajat, deci variază.');
    }
}

if (is_readable('/proc/meminfo')) {
    $mi = @file_get_contents('/proc/meminfo');
    if ($mi !== false && preg_match('/MemTotal:\s+(\d+) kB/', $mi, $m1)) {
        $ramT = (int)$m1[1] * 1024;
        $ramD = preg_match('/MemAvailable:\s+(\d+) kB/', $mi, $m2) ? (int)$m2[1] * 1024 : 0;
        rand_raport('Performanță', 'Memorie server', om($ramT) . ($ramD ? ' · disponibilă: ' . om($ramD) : ''));
    }
}

rand_raport('Performanță', 'Compresie ieșire (zlib)', ini_get('zlib.output_compression') ? 'pornită' : 'oprită', 'info');

rand_raport('Performanță', '➜ Încărcări simultane',
    'Fiecare încărcare ține un proces PHP ocupat cât durează transferul.',
    'info',
    'Cu poze micșorate în telefon (~2 MB), un transfer durează 1-3 secunde, deci procesele se eliberează repede. 100 de invitați deodată nu sunt o problemă.');

/* ============================================================
   7. DREPTURI DE SCRIERE
   ============================================================ */
foreach (['uploads/' => UPLOAD_DIR, 'uploads/thumbs/' => THUMB_DIR] as $et => $cale) {
    $exista = is_dir($cale);
    $scrie  = $exista && is_writable($cale);
    rand_raport('Drepturi de scriere', $et,
        !$exista ? 'LIPSEȘTE' : ($scrie ? 'se poate scrie' : 'NU se poate scrie'),
        $scrie ? 'ok' : 'rau',
        $exista ? ('Permisiuni: ' . substr(sprintf('%o', @fileperms($cale)), -4)) : 'Creează folderul.');
    if (!$scrie) problema('Folderul „' . $et . '" nu permite scrierea. Pune permisiuni 755 din cPanel → File Manager.');
}
$htUp = UPLOAD_DIR . '.htaccess';
rand_raport('Drepturi de scriere', 'uploads/.htaccess (protecție)', is_file($htUp) ? 'prezent' : 'LIPSEȘTE',
    is_file($htUp) ? 'ok' : 'rau', 'Împiedică rularea de cod din folderul de încărcări.');
if (!is_file($htUp)) problema('Lipsește uploads/.htaccess — fără el, cineva ar putea încărca un fișier periculos. Reîncarcă-l din arhivă.');

/* ============================================================
   8. EXTENSII PHP
   ============================================================ */
$ext = get_loaded_extensions();
sort($ext, SORT_NATURAL | SORT_FLAG_CASE);
$necesare = ['pdo_mysql' => 'baza de date', 'gd' => 'miniaturi', 'json' => 'comunicare cu pagina',
             'mbstring' => 'text cu diacritice', 'fileinfo' => 'tipuri de fișiere', 'zip' => 'descărcare album ZIP',
             'exif' => 'rotirea pozelor', 'openssl' => 'nume de fișiere sigure'];
foreach ($necesare as $e => $rol) {
    $are = extension_loaded($e);
    rand_raport('Extensii necesare', $e, $are ? 'da' : 'NU', $are ? 'ok' : ($e === 'exif' ? 'atentie' : 'rau'), $rol);
    if (!$are && $e !== 'exif') problema('Extensia „' . $e . '" lipsește (necesară pentru: ' . $rol . '). Activeaz-o din cPanel → Select PHP Version → Extensions.');
}

/* ============================================================
   Recomandări de valori pentru .user.ini
   ============================================================ */
$recomandari = [
    'upload_max_filesize' => '1024M',
    'post_max_size'       => '1024M',
    'memory_limit'        => '256M',
    'max_execution_time'  => '600',
    'max_input_time'      => '600',
    'max_file_uploads'    => '50',
];

/* Rezumat text, de copiat ușor */
$rezumat = "=== DIAGNOSTIC " . SITE_URL . " · " . date('d.m.Y H:i') . " ===\n";
$rezumat .= "PHP $phpVer ($sapi) · " . (string)($_SERVER['SERVER_SOFTWARE'] ?? '?') . "\n";
$rezumat .= "upload_max_filesize=" . ini_get('upload_max_filesize')
          . " post_max_size=" . ini_get('post_max_size')
          . " memory_limit=" . ini_get('memory_limit')
          . " max_execution_time=" . $maxEx
          . " max_input_time=" . $maxIn
          . " max_file_uploads=" . $maxFi . "\n";
$rezumat .= "LIMITA REALA pe fisier: " . om((int)$limitaReala) . " (config: " . om(MAX_FILE_SIZE) . ")\n";
$rezumat .= "GD=" . ($areGd ? 'da' : 'NU') . " EXIF=" . ($areExif ? 'da' : 'NU') . " Imagick=" . ($areImagick ? 'da' : 'nu')
          . " OPcache=" . (!is_array($op) ? 'indisponibil' : (!empty($op['opcache_enabled']) ? 'pornit' : 'oprit')) . "\n";
if ($liber !== false && $total !== false) {
    $rezumat .= "Disc: liber " . om((int)$liber) . " din " . om((int)$total) . " · uploads/ = " . om($dimUploads) . " ($nrFisiere fisiere)\n";
}
$rezumat .= "CPU: " . ($nuclee ?: '?') . " nuclee\n";
$rezumat .= "Probleme gasite: " . count($probleme) . "\n";
foreach ($probleme as $i => $p) $rezumat .= ($i + 1) . ". $p\n";

$culori = ['ok' => '#1B7F4E', 'atentie' => '#B5730F', 'rau' => '#B3261E', 'info' => '#5A6B72'];
$simbol = ['ok' => '✓', 'atentie' => '!', 'rau' => '✕', 'info' => '·'];
?><!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Diagnostic server · <?= h(NUME_MIRE) ?> &amp; <?= h(NUME_MIREASA) ?></title>
<style>
  *{box-sizing:border-box}
  body{margin:0;padding:24px 16px 60px;background:#F6F4F0;color:#2C2722;
       font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
  .wrap{max-width:920px;margin:0 auto}
  h1{font-size:1.5rem;margin:0 0 4px}
  .sub{color:#6B7A80;font-size:.9rem;margin-bottom:22px}
  .bara{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:22px}
  .btn{display:inline-block;padding:9px 16px;border-radius:9px;text-decoration:none;font-size:.88rem;
       border:1px solid #D8D2C8;background:#fff;color:#2C2722;cursor:pointer}
  .btn.p{background:#0D3328;border-color:#0D3328;color:#fff}
  .card{background:#fff;border:1px solid #E4DFD6;border-radius:14px;margin-bottom:18px;overflow:hidden}
  .card h2{font-size:.78rem;letter-spacing:.14em;text-transform:uppercase;color:#6B7A80;
           margin:0;padding:14px 18px;border-bottom:1px solid #EFEBE4;background:#FBFAF7}
  table{width:100%;border-collapse:collapse}
  td{padding:10px 18px;border-bottom:1px solid #F2EFE9;vertical-align:top}
  tr:last-child td{border-bottom:none}
  td.et{width:38%;color:#4A5560}
  td.vl{font-weight:600;width:34%;word-break:break-word}
  td.st{width:28px;text-align:center;font-weight:700}
  .nota{display:block;font-weight:400;font-size:.83rem;color:#7A868C;margin-top:3px}
  .alerta{background:#FFF6F5;border:1px solid #F3C9C5;border-radius:14px;padding:18px;margin-bottom:18px}
  .alerta h2{margin:0 0 10px;font-size:1rem;color:#B3261E}
  .alerta ol{margin:0;padding-left:20px}
  .alerta li{margin-bottom:8px}
  .bine{background:#F1F9F4;border:1px solid #BFE3CE;border-radius:14px;padding:18px;margin-bottom:18px;color:#1B7F4E}
  pre{background:#22201D;color:#E8E4DC;padding:16px;border-radius:12px;overflow:auto;font-size:.8rem;line-height:1.5}
  code{background:#F0EDE7;padding:2px 6px;border-radius:5px;font-size:.86rem}
  .mic{font-size:.85rem;color:#6B7A80}
</style>
</head>
<body>
<div class="wrap">

  <h1>Diagnostic server</h1>
  <div class="sub">
    <?= h(SITE_URL) ?> · generat <?= date('d.m.Y H:i') ?> ·
    pagina e vizibilă doar pentru administrator
  </div>

  <div class="bara">
    <a class="btn" href="admin.php">← Înapoi în panou</a>
    <button class="btn" onclick="copiaza()">Copiază rezumatul</button>
    <?php if (empty($_GET['full'])): ?>
      <a class="btn" href="?full=1">Arată tot phpinfo()</a>
    <?php else: ?>
      <a class="btn" href="info.php">Ascunde phpinfo()</a>
    <?php endif; ?>
  </div>

  <?php if ($probleme): ?>
    <div class="alerta">
      <h2>De reglat (<?= count($probleme) ?>)</h2>
      <ol><?php foreach ($probleme as $p): ?><li><?= h($p) ?></li><?php endforeach; ?></ol>
    </div>
  <?php else: ?>
    <div class="bine"><strong>✓ Totul arată bine.</strong> Nu am găsit nimic de reglat.</div>
  <?php endif; ?>

  <?php foreach ($raport as $sectiune => $randuri): ?>
    <div class="card">
      <h2><?= h($sectiune) ?></h2>
      <table>
        <?php foreach ($randuri as $r): ?>
          <tr>
            <td class="st" style="color:<?= $culori[$r['stare']] ?>"><?= $simbol[$r['stare']] ?></td>
            <td class="et"><?= h($r['eticheta']) ?></td>
            <td class="vl" colspan="2">
              <?= h($r['valoare']) ?>
              <?php if ($r['nota']): ?><span class="nota"><?= h($r['nota']) ?></span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endforeach; ?>

  <div class="card">
    <h2>Valori recomandate</h2>
    <table>
      <?php
      /* „0" la timpi și „-1" înseamnă nelimitat — adică mai bun decât recomandarea. */
      $fara_limita = ['max_execution_time' => [0], 'max_input_time' => [0, -1]];
      foreach ($recomandari as $k => $v):
        $acum = (string)ini_get($k);
        $potrivit = isset($fara_limita[$k]) && in_array((int)$acum, $fara_limita[$k], true)
                    ? true
                    : ini_octeti($acum) >= ini_octeti($v);
      ?>
        <tr>
          <td class="st" style="color:<?= $potrivit ? $culori['ok'] : $culori['atentie'] ?>"><?= $potrivit ? '✓' : '!' ?></td>
          <td class="et"><code><?= h($k) ?></code></td>
          <td class="vl" colspan="2">
            <?= h($v) ?>
            <span class="nota">acum: <?= h($acum !== '' ? $acum : '(gol)') ?></span>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="card">
    <h2>Cum schimbi valorile în cPanel</h2>
    <table><tr><td colspan="4">
      <p style="margin-top:0"><strong>Varianta 1 (cea mai simplă):</strong> cPanel →
        <em>MultiPHP INI Editor</em> → alege domeniul → pune valorile din tabelul de mai sus → <em>Apply</em>.</p>
      <p><strong>Varianta 2:</strong> creezi un fișier <code>.user.ini</code> în <code>public_html</code> cu:</p>
      <pre><?php foreach ($recomandari as $k => $v) { echo h($k) . ' = ' . h($v) . "\n"; } ?></pre>
      <p class="mic">Schimbările din <code>.user.ini</code> se aplică în câteva minute (PHP le recitește periodic).
        Dacă gazda nu îți permite valori atât de mari, scrie-le pe cele mai mari acceptate și spune-mi ce a ieșit —
        ajustăm aplicația să fie sinceră cu invitații.</p>
    </td></tr></table>
  </div>

  <div class="card">
    <h2>Rezumat de copiat</h2>
    <table><tr><td colspan="4">
      <p class="mic" style="margin-top:0">Apasă „Copiază rezumatul" de sus și trimite-mi textul — știu exact ce să reglez.</p>
      <pre id="rezumat"><?= h($rezumat) ?></pre>
    </td></tr></table>
  </div>

  <?php if (!empty($_GET['full'])): ?>
    <div class="card">
      <h2>phpinfo() complet</h2>
      <div style="padding:12px 18px;overflow-x:auto">
        <?php
        ob_start();
        phpinfo();
        $pi = (string)ob_get_clean();
        // păstrăm doar conținutul din <body>, ca să nu strice pagina
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $pi, $m)) $pi = $m[1];
        $pi = preg_replace('/<a\s+href="http:\/\/www\.php\.net\/"><img[^>]*><\/a>/i', '', $pi);
        echo '<style>#phpinfo table{width:100%;font-size:.8rem}#phpinfo td,#phpinfo th{padding:4px 8px;border:1px solid #EFEBE4;word-break:break-all}</style>';
        echo '<div id="phpinfo">' . $pi . '</div>';
        ?>
      </div>
    </div>
  <?php endif; ?>

  <p class="mic">
    Fișierul e protejat prin autentificare. Dacă vrei să dispară complet, șterge <code>info.php</code> din cPanel.
  </p>
</div>

<script>
function copiaza() {
  var t = document.getElementById('rezumat').innerText;
  function gata() { alert('Rezumatul a fost copiat. Trimite-l mai departe.'); }
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(t).then(gata, manual);
  } else { manual(); }
  function manual() {
    var a = document.createElement('textarea');
    a.value = t; document.body.appendChild(a); a.select();
    try { document.execCommand('copy'); gata(); } catch (e) { alert('Selectează textul manual și copiază-l.'); }
    document.body.removeChild(a);
  }
}
</script>
</body>
</html>
