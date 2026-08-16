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
$sapiBun = (stripos($sapi, 'lsapi') !== false || stripos($sapi, 'litespeed') !== false
            || stripos($sapi, 'fpm') !== false || stripos($sapi, 'cgi') !== false);
rand_raport('Server și PHP', 'Interfață PHP (SAPI)', $sapi,
    $sapiBun ? 'ok' : 'atentie',
    (stripos($sapi, 'lsapi') !== false || stripos($sapi, 'litespeed') !== false)
        ? 'LiteSpeed — ideal pentru încărcări multe în paralel.' : '');

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

/* Filmele nu mai pleacă într-o singură cerere: se trimit pe bucăți.
   Deci limita serverului pe cerere mărginește doar bucata, nu filmul
   întreg — un film de 4 GB încape chiar dacă serverul acceptă 1 GB
   pe cerere. Mărginite de limita pe cerere rămân doar pozele, care
   merg dintr-o bucată; dar ele sunt micșorate în telefon la 1-2 MB. */
$bucata = dimensiune_bucata();

rand_raport('Limite de încărcare', 'Bucata la filmele mari', om($bucata),
    $bucata <= $limitaServer ? 'ok' : 'rau',
    'Filmele se trimit pe bucăți de atât, deci mărimea lor totală nu e îngrădită de limita pe cerere.');

rand_raport('Limite de încărcare', '➜ LIMITA REALĂ, film', om(MAX_FILE_SIZE),
    'ok', 'Se trimite pe bucăți, deci contează doar spațiul pe disc și răbdarea invitatului.');

rand_raport('Limite de încărcare', '➜ LIMITA REALĂ, poză', om((int)$limitaServer),
    $limitaServer >= 33554432 ? 'ok' : 'atentie',
    'Pozele merg dintr-o bucată, dar se micșorează în telefon la 1-2 MB.');

rand_raport('Limite de încărcare', 'Limită per invitat', 'NU există',
    'ok', 'Fiecare invitat poate încărca oricâte fișiere. Singura limită e spațiul pe disc.');

if ($pms > 0 && $pms < $umf) {
    problema('post_max_size (' . om($pms) . ') e mai mic decât upload_max_filesize (' . om($umf) . ') — limita reală scade la ' . om($pms) . '. Fă-le egale.');
}
/* Singura nepotrivire care chiar strică: bucata nu încape în cerere. */
if ($limitaServer > 0 && $bucata > $limitaServer) {
    problema('Bucata folosită la filme (' . om($bucata) . ') e mai mare decât acceptă serverul într-o cerere ('
        . om($limitaServer) . '). Toate încărcările de filme ar eșua.');
}
if ($maxIn >= 0 && $maxIn < 300) {
    problema('max_input_time = ' . $maxIn . 's e mic. Un film de 500 MB pe 4G lent poate depăși timpul și se rupe încărcarea. Recomand 600 sau -1.');
}
if ($mem >= 0 && $mem < 268435456) {
    problema('memory_limit = ' . om($mem) . '. La poze foarte mari (peste 20 MP) facerea miniaturii poate pica. Recomand 256M.');
}

/* Ce îi trebuie APLICAȚIEI, nu ce ar fi frumos să fie. Filmele se trimit
   pe bucăți de 8 MB, deci nu mai are rost să cerem limite uriașe pe o
   cerere — cerem doar cât să încapă bucata, cu ceva marjă. */
$minime = [
    'upload_max_filesize' => ['minim' => $bucata * 2, 'text' => om($bucata * 2), 'de_ce' => 'trebuie să încapă o bucată de ' . om($bucata)],
    'post_max_size'       => ['minim' => $bucata * 2, 'text' => om($bucata * 2), 'de_ce' => 'la fel: o bucată plus antetul cererii'],
    'memory_limit'        => ['minim' => 256 * 1024 * 1024, 'text' => '256M', 'de_ce' => 'miniatura unei poze mari nemicșorate'],
    'max_execution_time'  => ['minim' => 60,  'text' => '60 s',  'de_ce' => 'prelucrarea unei bucăți durează secunde'],
    'max_input_time'      => ['minim' => 120, 'text' => '120 s', 'de_ce' => 'primirea unei bucăți pe conexiune slabă'],
    'max_file_uploads'    => ['minim' => 5,   'text' => '5',     'de_ce' => 'aplicația trimite un fișier (plus miniatura filmului)'],
];

/* Verificăm dacă valorile ACTIVE ajung. Doar dacă nu ajung avem o problemă. */
$subMinim = [];
foreach ($minime as $cheie => $m) {
    $acum = (string)ini_get($cheie);
    $val  = preg_match('/[kmg]$/i', $acum) ? ini_octeti($acum) : (int)$acum;
    if ($val === 0 || $val === -1) continue;              // nelimitat
    if ($val < $m['minim']) $subMinim[] = $cheie;
}

/* ============================================================
   2b. FIȘIERUL .user.ini — se aplică sau nu?
   ------------------------------------------------------------
   Fișierul se citește doar din folderul site-ului (și din
   subfolderele lui). Pus lângă alt domeniu, nu are niciun efect —
   iar asta nu se vede nicăieri, decât comparând valorile scrise cu
   cele pe care le raportează serverul.
   ============================================================ */
$numeUserIni = (string)(ini_get('user_ini.filename') ?: '.user.ini');
$caleUserIni = __DIR__ . '/' . $numeUserIni;

rand_raport('Fișierul .user.ini', 'Numele căutat de PHP', $numeUserIni, 'info',
    'Se citește din folderul site-ului. Reîmprospătarea durează până la '
    . (int)ini_get('user_ini.cache_ttl') . ' secunde.');

if (!is_file($caleUserIni)) {
    rand_raport('Fișierul .user.ini', 'Există lângă aplicație?', 'NU', 'info',
        'Se folosesc valorile serverului. Dacă ai editat un ' . $numeUserIni
        . ' și nu vezi schimbările, e pus în alt folder decât ' . __DIR__);
} else {
    rand_raport('Fișierul .user.ini', 'Există lângă aplicație?', 'da', 'ok', $caleUserIni);

    $scrise = @parse_ini_file($caleUserIni, false, INI_SCANNER_RAW) ?: [];
    $nepotrivite = 0;
    foreach ($scrise as $cheie => $valoare) {
        $cheie = trim((string)$cheie);
        if (!in_array($cheie, ['upload_max_filesize','post_max_size','memory_limit',
                               'max_execution_time','max_input_time','max_file_uploads'], true)) continue;
        $acum  = (string)ini_get($cheie);
        $scris = trim((string)$valoare);
        /* comparăm ca octeți unde are sens, altfel ca numere */
        $laFel = preg_match('/[kmg]$/i', $scris)
               ? ini_octeti($scris) === ini_octeti($acum)
               : (int)$scris === (int)$acum;
        if (!$laFel) $nepotrivite++;
        rand_raport('Fișierul .user.ini', $cheie, 'scris ' . $scris . ' · activ ' . ($acum !== '' ? $acum : '(gol)'),
            $laFel ? 'ok' : 'rau', $laFel ? 'se aplică' : 'NU se aplică');
    }
    if ($nepotrivite > 0) {
        /* Că fișierul nu se aplică e supărător, dar nu e o defecțiune atâta
           vreme cât valorile active ajung aplicației. Problemă declarăm doar
           dacă lipsește ceva cu adevărat. */
        $textUserIni = 'Cele ' . $nepotrivite . ' valori din ' . $numeUserIni
            . ' nu se aplică — găzduirea nu permite schimbarea lor din acest fișier';
        if (empty($subMinim)) {
            rand_raport('Fișierul .user.ini', '➜ Contează?', 'NU', 'ok',
                $textUserIni . '. Valorile active ale serverului sunt însă peste ce cere aplicația, deci nu ai nimic de făcut. Poți șterge fișierul.');
        } else {
            problema($textUserIni . ', iar valorile active NU ajung pentru: '
                . implode(', ', $subMinim) . '. Cere-i gazdei să le mărească.');
        }
    }
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
   4b. STRUCTURA BAZEI DE DATE
   ------------------------------------------------------------
   Migrarea automată e scrisă să nu dărâme site-ul dacă ceva nu
   merge: înghite erorile în tăcere. Bine pentru invitați, dar
   înseamnă că numărul de versiune poate fi salvat chiar dacă un
   index n-a apucat să se creeze. De aceea verificăm structurile
   una câte una, nu versiunea.

   Ce lipsește apare mai jos ca text SQL, gata de rulat în
   phpMyAdmin — cazul tipic e un utilizator de bază de date fără
   drept de ALTER.
   ============================================================ */
$sqlDeRulat = [];

if (isset($pdo)) {
    $cauta = function (string $sql, array $val) use ($pdo): ?int {
        try {
            $st = $pdo->prepare($sql);
            $st->execute($val);
            return (int)$st->fetchColumn();
        } catch (Throwable $e) { return null; }   // null = n-am putut verifica
    };
    $areTabela = fn(string $t) => $cauta(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$t]);
    $areColoana = fn(string $t, string $c) => $cauta(
        'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$t, $c]);
    $areIndex = fn(string $t, string $i) => $cauta(
        'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?', [$t, $i]);

    /* Ce are nevoie aplicația, cu reparația alături. */
    $sqlAprecieri = "CREATE TABLE IF NOT EXISTS aprecieri (\n"
        . "  poza_id     INT NOT NULL,\n"
        . "  jeton       VARCHAR(64) NOT NULL,\n"
        . "  data_creare TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  PRIMARY KEY (poza_id, jeton),\n"
        . "  INDEX idx_poza (poza_id)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $asteptat = [
        ['tabela',  'poze',      null,              'fotografiile și filmele',      null],
        ['tabela',  'setari',    null,              'setările din panou',           null],
        ['tabela',  'urari',     null,              'cartea de urări',              null],
        ['tabela',  'aprecieri', null,              'inimioarele, una per invitat', $sqlAprecieri],

        ['coloana', 'poze',  'aprecieri',       'numărul de aprecieri',
            'ALTER TABLE poze ADD COLUMN aprecieri INT UNSIGNED NOT NULL DEFAULT 0;'],
        ['coloana', 'poze',  'jeton',           'cine a încărcat (pentru ștergere)',
            'ALTER TABLE poze ADD COLUMN jeton VARCHAR(64) DEFAULT NULL;'],
        ['coloana', 'poze',  'amprenta_fisier', 'depistarea duplicatelor',
            'ALTER TABLE poze ADD COLUMN amprenta_fisier VARCHAR(64) DEFAULT NULL;'],
        ['coloana', 'urari', 'jeton',           'cine a scris urarea',
            'ALTER TABLE urari ADD COLUMN jeton VARCHAR(64) DEFAULT NULL;'],

        ['index', 'poze',      'idx_galerie',   'galeria, sortare după dată',
            'CREATE INDEX idx_galerie ON poze (aprobat, data_incarcare, id);'],
        ['index', 'poze',      'idx_apreciate', 'galeria, cele mai apreciate',
            'CREATE INDEX idx_apreciate ON poze (aprobat, aprecieri, data_incarcare);'],
        ['index', 'poze',      'idx_jeton',     'căutarea fișierelor proprii',
            'CREATE INDEX idx_jeton ON poze (jeton);'],
        ['index', 'poze',      'idx_amprenta',  'căutarea duplicatelor',
            'CREATE INDEX idx_amprenta ON poze (amprenta_fisier);'],
        ['index', 'urari',     'idx_aprobat',   'lista de urări',
            'CREATE INDEX idx_aprobat ON urari (aprobat, data_creare);'],
        ['index', 'urari',     'idx_jeton',     'urările proprii',
            'CREATE INDEX idx_jeton ON urari (jeton);'],
        ['index', 'aprecieri', 'idx_poza',      'numărarea aprecierilor',
            'CREATE INDEX idx_poza ON aprecieri (poza_id);'],
    ];

    $lipsesc = 0;
    foreach ($asteptat as [$fel, $tabela, $nume, $rol, $sqlFix]) {
        if ($fel === 'tabela')      { $n = $areTabela($tabela);        $et = 'Tabela ' . $tabela; }
        elseif ($fel === 'coloana') { $n = $areColoana($tabela, $nume); $et = $tabela . '.' . $nume; }
        else                        { $n = $areIndex($tabela, $nume);   $et = 'Index ' . $nume . ' (' . $tabela . ')'; }

        if ($n === null) {
            rand_raport('Structura bazei de date', $et, 'nu s-a putut verifica', 'atentie', $rol);
            continue;
        }
        if ($n > 0) {
            rand_raport('Structura bazei de date', $et, 'există', 'ok', $rol);
            continue;
        }
        /* Un index lipsă încetinește; o tabelă sau o coloană lipsă strică funcții. */
        $grav = $fel === 'index' ? 'atentie' : 'rau';
        rand_raport('Structura bazei de date', $et, 'LIPSEȘTE', $grav, $rol);
        $lipsesc++;
        if ($sqlFix) $sqlDeRulat[] = $sqlFix;
    }

    $ver = (int)setare('schema_v', '0');
    rand_raport('Structura bazei de date', 'Versiunea schemei', (string)$ver,
        $ver >= 6 ? 'ok' : 'atentie',
        'Doar informativ: migrarea înghite erorile, deci numărul poate fi corect chiar dacă ceva lipsește. Contează rândurile de mai sus.');

    if ($lipsesc > 0) {
        problema($lipsesc . ' element(e) lipsesc din baza de date. Rulează în phpMyAdmin comenzile SQL afișate mai jos — cel mai probabil utilizatorul bazei de date nu are drept de ALTER.');
    }
} else {
    rand_raport('Structura bazei de date', 'Verificare', 'imposibilă', 'rau', 'Nu există conexiune la baza de date.');
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
/* Fișiere rămase pe disc fără rând în baza de date. Se întâmplă după
   ștergeri făcute direct din File Manager sau după o reinstalare a
   bazei: ocupă spațiu degeaba și nu se văd nicăieri în album. */
if ($nrFisiere > 0 && isset($pdo)) {
    try {
        $inBd = (int)$pdo->query('SELECT COUNT(*) FROM poze')->fetchColumn();
        /* fiecare poză are fișierul ei plus, de obicei, o miniatură */
        $orfane = $nrFisiere - ($inBd * 2);
        if ($inBd === 0 && $nrFisiere > 2) {
            rand_raport('Spațiu pe disc', 'Fișiere fără rând în album', (string)$nrFisiere, 'atentie',
                'Baza de date nu are nicio poză, dar în uploads/ sunt ' . $nrFisiere
                . ' fișiere care ocupă ' . om($dimUploads) . '. Sunt rămase din încercări: se pot șterge liniștit prin File Manager.');
            problema('În uploads/ au rămas ' . $nrFisiere . ' fișiere (' . om($dimUploads)
                . ') care nu apar în album, pentru că baza de date e goală. Șterge conținutul folderului uploads/ (păstrează index.html, .htaccess și folderul thumbs) ca să pornești curat.');
        } elseif ($orfane > 5) {
            rand_raport('Spațiu pe disc', 'Fișiere fără rând în album', '~' . $orfane, 'atentie',
                'Mai multe fișiere pe disc decât în baza de date. Probabil rămase după ștergeri făcute din File Manager.');
        }
    } catch (Throwable $e) { /* fără baza de date nu putem compara */ }
}

rand_raport('Spațiu pe disc', 'Atenție la cifrele de mai sus',
    'sunt ale întregului server, nu ale contului tău', 'atentie',
    'Pe găzduire partajată discul e împărțit cu alții. Cifra care te privește e cota contului, din cPanel → Disk Usage.');

rand_raport('Spațiu pe disc', '➜ Cota contului (DISK_QUOTA_GB)', DISK_QUOTA_GB . ' GB', 'info',
    'Asta e limita ta adevărată. Actualizeaz-o în config.php când cumperi spațiu.');

$ocupat = $nrFisiere >= 0 ? $dimUploads : 0;
$cotaOcteti = DISK_QUOTA_GB * 1024 * 1024 * 1024;
$procCota = $cotaOcteti > 0 ? round($ocupat / $cotaOcteti * 100, 1) : 0;
rand_raport('Spațiu pe disc', 'Ocupat de album',
    ($nrFisiere >= 0 ? om($ocupat) . ' · ' . $nrFisiere . ' fișiere' : 'nu s-a putut citi')
    . ($nrFisiere >= 0 ? '  (' . $procCota . '% din cotă)' : ''),
    $procCota < 75 ? 'ok' : ($procCota < 90 ? 'atentie' : 'rau'));

/* Estimare, raportată la cota contului — nu la discul serverului. */
if ($nrFisiere >= 0) {
    $ramas = max(0, $cotaOcteti - $ocupat);
    $estPoze  = (int)floor($ramas / (2 * 1024 * 1024));     // ~2 MB/poză după micșorare
    $estFilme = (int)floor($ramas / (60 * 1024 * 1024));    // ~60 MB/film scurt
    rand_raport('Spațiu pe disc', '➜ Mai încap aproximativ',
        number_format($estPoze, 0, ',', '.') . ' poze SAU ' . number_format($estFilme, 0, ',', '.') . ' filme scurte',
        'info', 'Din cota ta rămasă (' . om($ramas) . '): ~2 MB/poză, ~60 MB/film de un minut.');
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

/* Rezumat text, de copiat ușor */
$rezumat = "=== DIAGNOSTIC " . SITE_URL . " · " . date('d.m.Y H:i') . " ===\n";
$rezumat .= "PHP $phpVer ($sapi) · " . (string)($_SERVER['SERVER_SOFTWARE'] ?? '?') . "\n";
$rezumat .= "upload_max_filesize=" . ini_get('upload_max_filesize')
          . " post_max_size=" . ini_get('post_max_size')
          . " memory_limit=" . ini_get('memory_limit')
          . " max_execution_time=" . $maxEx
          . " max_input_time=" . $maxIn
          . " max_file_uploads=" . $maxFi . "\n";
$rezumat .= "Limita film: " . om(MAX_FILE_SIZE) . " (pe bucati de " . om($bucata)
          . ") | limita poza: " . om((int)$limitaServer) . "\n";
$rezumat .= "GD=" . ($areGd ? 'da' : 'NU') . " EXIF=" . ($areExif ? 'da' : 'NU') . " Imagick=" . ($areImagick ? 'da' : 'nu')
          . " OPcache=" . (!is_array($op) ? 'indisponibil' : (!empty($op['opcache_enabled']) ? 'pornit' : 'oprit')) . "\n";
/* Raportăm cota contului, nu discul serverului: acela e împărțit cu alți
   clienți, arată zeci de terabiți și nu spune nimic despre cât ai tu. */
$rezumat .= "Album: " . om($ocupat) . " din cota de " . DISK_QUOTA_GB . " GB"
          . " (" . $procCota . "%) · " . max(0, $nrFisiere) . " fisiere"
          . " · mai incap " . om(max(0, $cotaOcteti - $ocupat)) . "\n";
$rezumat .= "CPU: " . ($nuclee ?: '?') . " nuclee\n";
/* Fără conexiune nu s-a verificat nimic — a spune „completă" ar fi o
   liniștire falsă, exact ce reproșăm numărului de versiune. */
$rezumat .= "Structura BD: " . (!isset($pdo)
    ? "NEVERIFICATA (fara conexiune la baza de date)"
    : (empty($sqlDeRulat) ? "completa" : count($sqlDeRulat) . " elemente lipsa (vezi SQL de reparatie)")) . "\n";
$rezumat .= "Probleme gasite: " . count($probleme) . "\n";
foreach ($probleme as $i => $p) $rezumat .= ($i + 1) . ". $p\n";

$culori = ['ok' => '#1B7F4E', 'atentie' => '#B5730F', 'rau' => '#B3261E', 'info' => '#5A6B72'];
$simbol = ['ok' => '✓', 'atentie' => '!', 'rau' => '✕', 'info' => '·'];
?><!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="icon" href="assets/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
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

  <?php if (!empty($sqlDeRulat)): ?>
    <div class="card">
      <h2>SQL de reparație</h2>
      <table><tr><td colspan="4">
        <p style="margin-top:0">
          Lipsesc structuri din baza de date. Deschide <strong>cPanel → phpMyAdmin</strong>,
          alege baza <code><?= h(DB_NAME) ?></code>, intră pe fila <strong>SQL</strong>
          și rulează:
        </p>
        <pre><?= h(implode("\n", $sqlDeRulat)) ?></pre>
        <p class="mic">
          După ce le rulezi, reîncarcă această pagină: rândurile de mai sus trebuie să devină toate verzi.
        </p>
      </td></tr></table>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2>De cât are nevoie aplicația</h2>
    <table>
      <tr><td colspan="4" style="padding-bottom:4px">
        <span class="mic">Filmele se trimit pe bucăți de <?= h(om($bucata)) ?>, deci nu sunt necesare
        limite uriașe pe o cerere. Mai jos e minimul de care are nevoie aplicația —
        nu ce ar fi frumos să fie.</span>
      </td></tr>
      <?php foreach ($minime as $k => $m):
        $acum = (string)ini_get($k);
        $val  = preg_match('/[kmg]$/i', $acum) ? ini_octeti($acum) : (int)$acum;
        $nelimitat = ($val === 0 || $val === -1);
        $potrivit  = $nelimitat || $val >= $m['minim'];
      ?>
        <tr>
          <td class="st" style="color:<?= $potrivit ? $culori['ok'] : $culori['rau'] ?>"><?= $potrivit ? '✓' : '✕' ?></td>
          <td class="et"><code><?= h($k) ?></code></td>
          <td class="vl" colspan="2">
            ai <?= h($acum !== '' ? $acum : '(gol)') ?>, trebuie cel puțin <?= h($m['text']) ?>
            <span class="nota"><?= h($m['de_ce']) ?></span>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <?php if (!empty($subMinim)): ?>
  <div class="card">
    <h2>Cum mărești valorile în cPanel</h2>
    <table><tr><td colspan="4">
      <p style="margin-top:0">
        Valorile active nu ajung pentru: <strong><?= h(implode(', ', $subMinim)) ?></strong>.
        Mergi în cPanel → <em>MultiPHP INI Editor</em>, alege
        <strong><?= h((string)($_SERVER['HTTP_HOST'] ?? '')) ?></strong> și pune cel puțin:
      </p>
      <pre><?php foreach ($subMinim as $k) { echo h($k) . ' = ' . h($minime[$k]['text']) . "\n"; } ?></pre>
      <p class="mic">Dacă găzduirea nu permite, cere-i gazdei să le mărească ea — sunt valori mici,
        obișnuite. Un fișier <code>.user.ini</code> pus de tine poate fi ignorat, iar atunci nu se
        vede nicăieri că a fost ignorat.</p>
    </td></tr></table>
  </div>
  <?php endif; ?>

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
