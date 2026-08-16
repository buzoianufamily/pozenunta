<?php
/* ============================================================
   INSTALARE / REPARARE A BAZEI DE DATE
   ------------------------------------------------------------
   Creează tot ce are nevoie aplicația: tabele, coloane, indexuri.

   Trei lucruri de știut:

   1. NU ȘTERGE NICIODATĂ DATE. Creează doar ce lipsește, deci
      poate fi rulat oricând, chiar și cu albumul plin de poze.
   2. Cere autentificare de administrator. Datele de intrare vin
      din config.php, nu din baza de date, deci merge inclusiv
      când baza de date e goală.
   3. Poate fi rulat de câte ori vrei — a doua oară nu mai are
      ce face și ți-o spune.

   După nuntă poți șterge fișierul; nu e obligatoriu, e protejat.
   ============================================================ */
require_once __DIR__ . '/functions.php';
cere_admin();

/* Conexiune proprie: db() din config.php face die() la eroare, iar aici
   avem nevoie să explicăm frumos ce e de corectat în config.php. */
$pdo = null;
$eroareBd = null;
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) {
    $eroareBd = $e->getMessage();
}

/* ------------------------------------------------------------
   Verificări stricte: null înseamnă „nu am putut afla", ceea ce
   nu e totuna cu „nu există".
   ------------------------------------------------------------ */
function nr(?PDO $pdo, string $sql, array $val): ?int {
    if (!$pdo) return null;
    try { $s = $pdo->prepare($sql); $s->execute($val); return (int)$s->fetchColumn(); }
    catch (Throwable $e) { return null; }
}
function are_tabela(?PDO $p, string $t): ?int {
    return nr($p, 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$t]);
}
function are_coloana(?PDO $p, string $t, string $c): ?int {
    return nr($p, 'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$t, $c]);
}
function are_index(?PDO $p, string $t, string $i): ?int {
    return nr($p, 'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?', [$t, $i]);
}

/* Coloana care ține mărimea fișierului trebuie să încapă filme mari.
   Un INT obișnuit se oprește la 2 GB, iar tabela veche, creată de
   instalarea dinainte, are de obicei INT. Peste 2 GB, mărimea s-ar
   salva greșit sau ar da eroare. */
function marime_incape_mare(?PDO $p): ?int {
    if (!$p) return null;
    try {
        $st = $p->prepare(
            'SELECT DATA_TYPE FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $st->execute(['poze', 'marime']);
        $tip = $st->fetchColumn();
        if ($tip === false) return 0;                      // coloana lipsește
        return strtolower((string)$tip) === 'bigint' ? 1 : 0;
    } catch (Throwable $e) { return null; }
}

/* ============================================================
   TOT CE ARE NEVOIE APLICAȚIA
   Ordinea contează: întâi tabelele, apoi coloanele, apoi indexurile.
   ============================================================ */
$pasi = [];

/* ---------- tabele ---------- */
$pasi[] = ['tabela', 'poze', null, 'Fotografiile și filmele',
"CREATE TABLE IF NOT EXISTS poze (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  nume_fisier     VARCHAR(255) NOT NULL,
  nume_original   VARCHAR(255) DEFAULT NULL,
  nume_invitat    VARCHAR(120) DEFAULT NULL,
  mesaj           TEXT DEFAULT NULL,
  tip             VARCHAR(10) NOT NULL DEFAULT 'imagine',
  marime          BIGINT UNSIGNED NOT NULL DEFAULT 0,
  aprobat         TINYINT(1) NOT NULL DEFAULT 1,
  ip              VARCHAR(45) DEFAULT NULL,
  data_incarcare  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  aprecieri       INT UNSIGNED NOT NULL DEFAULT 0,
  jeton           VARCHAR(64) DEFAULT NULL,
  amprenta_fisier VARCHAR(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"];

$pasi[] = ['tabela', 'setari', null, 'Setările din panou',
"CREATE TABLE IF NOT EXISTS setari (
  cheie   VARCHAR(64) NOT NULL PRIMARY KEY,
  valoare TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"];

$pasi[] = ['tabela', 'urari', null, 'Cartea de urări',
"CREATE TABLE IF NOT EXISTS urari (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  nume         VARCHAR(120) NOT NULL,
  mesaj        TEXT NOT NULL,
  aprobat      TINYINT(1) NOT NULL DEFAULT 1,
  ip           VARCHAR(45) DEFAULT NULL,
  data_creare  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  jeton        VARCHAR(64) DEFAULT NULL,
  INDEX idx_data (data_creare)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"];

$pasi[] = ['tabela', 'aprecieri', null, 'Aprecierile, una per invitat',
"CREATE TABLE IF NOT EXISTS aprecieri (
  poza_id     INT NOT NULL,
  jeton       VARCHAR(64) NOT NULL,
  data_creare TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (poza_id, jeton),
  INDEX idx_poza (poza_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"];

/* ---------- coloane adăugate pe parcurs ----------
   Contează pentru albumele instalate înainte, care au tabela
   „poze" veche, fără aceste coloane. */
$pasi[] = ['coloana', 'poze', 'aprecieri', 'Numărul de aprecieri',
    'ALTER TABLE poze ADD COLUMN aprecieri INT UNSIGNED NOT NULL DEFAULT 0'];
$pasi[] = ['coloana', 'poze', 'jeton', 'Cine a încărcat fișierul (pentru ștergere)',
    'ALTER TABLE poze ADD COLUMN jeton VARCHAR(64) DEFAULT NULL'];
$pasi[] = ['coloana', 'poze', 'amprenta_fisier', 'Depistarea duplicatelor',
    'ALTER TABLE poze ADD COLUMN amprenta_fisier VARCHAR(64) DEFAULT NULL'];
$pasi[] = ['coloana', 'urari', 'jeton', 'Cine a scris urarea',
    'ALTER TABLE urari ADD COLUMN jeton VARCHAR(64) DEFAULT NULL'];

/* Lărgirea coloanei de mărime, ca să încapă filme de peste 2 GB. */
$pasi[] = ['tip', 'poze', 'marime', 'Mărimea fișierului (filme mari)',
    'ALTER TABLE poze MODIFY marime BIGINT UNSIGNED NOT NULL DEFAULT 0'];

/* ---------- indexuri ---------- */
$pasi[] = ['index', 'poze', 'idx_galerie', 'Galeria, sortare după dată',
    'CREATE INDEX idx_galerie ON poze (aprobat, data_incarcare, id)'];
$pasi[] = ['index', 'poze', 'idx_apreciate', 'Galeria, cele mai apreciate',
    'CREATE INDEX idx_apreciate ON poze (aprobat, aprecieri, data_incarcare)'];
$pasi[] = ['index', 'poze', 'idx_jeton', 'Căutarea fișierelor proprii',
    'CREATE INDEX idx_jeton ON poze (jeton)'];
$pasi[] = ['index', 'poze', 'idx_amprenta', 'Căutarea duplicatelor',
    'CREATE INDEX idx_amprenta ON poze (amprenta_fisier)'];
$pasi[] = ['index', 'urari', 'idx_aprobat', 'Lista de urări',
    'CREATE INDEX idx_aprobat ON urari (aprobat, data_creare)'];
$pasi[] = ['index', 'urari', 'idx_jeton', 'Urările proprii',
    'CREATE INDEX idx_jeton ON urari (jeton)'];

/* Numele arătat în pagină pentru fiecare pas. */
function eticheta_pas(string $fel, string $tabela, ?string $nume): string {
    if ($fel === 'tabela')  return "Tabela $tabela";
    if ($fel === 'coloana') return "$tabela.$nume";
    if ($fel === 'tip')     return "$tabela.$nume (încape filme mari)";
    return "Index $nume ($tabela)";
}

/* Starea curentă a fiecărui pas: 1 există, 0 lipsește, null necunoscut.

   Atenție la numărătoare: în catalog, un index pe mai multe coloane apare
   cu câte un rând pentru fiecare coloană. Un index pe trei coloane dă
   „3", nu „1" — iar dacă am compara cu 1, l-am crede lipsă și am încerca
   să-l creăm peste el. De aceea aducem totul la 0 sau 1. */
function starea_pasului(?PDO $pdo, array $pas): ?int {
    [$fel, $tabela, $nume] = $pas;

    if ($fel === 'tabela') {
        $n = are_tabela($pdo, $tabela);
    } elseif ($fel === 'tip') {
        if (!are_tabela($pdo, $tabela)) return 0;
        $n = marime_incape_mare($pdo);
    } elseif ($fel === 'coloana') {
        if (!are_tabela($pdo, $tabela)) return 0;   // fără tabelă, nici coloana
        $n = are_coloana($pdo, $tabela, $nume);
    } else {
        if (!are_tabela($pdo, $tabela)) return 0;
        $n = are_index($pdo, $tabela, $nume);
    }

    return $n === null ? null : ($n > 0 ? 1 : 0);
}

/* ============================================================
   EXECUȚIA
   ============================================================ */
$rezultate = [];
$aRulat    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    if (!csrf_valid($_POST['csrf'] ?? '')) {
        $rezultate[] = ['rau', 'Sesiune expirată. Reîncarcă pagina și încearcă din nou.'];
    } else {
        $aRulat = true;
        foreach ($pasi as $pas) {
            [$fel, $tabela, $nume, $descriere, $sql] = $pas;
            $eticheta = eticheta_pas($fel, $tabela, $nume);

            if (starea_pasului($pdo, $pas) === 1) {
                $rezultate[] = ['sarit', "$eticheta — există deja"];
                continue;
            }
            try {
                $pdo->exec($sql);
                $rezultate[] = ['ok', "$eticheta — creat"];
            } catch (Throwable $e) {
                /* „Există deja" nu e o defecțiune: 1050 tabelă, 1060 coloană,
                   1061 index. Se poate întâmpla dacă verificarea din catalog
                   n-a văzut ceva ce serverul are. */
                $cod = ($e instanceof PDOException && isset($e->errorInfo[1])) ? (int)$e->errorInfo[1] : 0;
                if (in_array($cod, [1050, 1060, 1061], true)) {
                    $rezultate[] = ['sarit', "$eticheta — există deja"];
                } else {
                    $rezultate[] = ['rau', "$eticheta — NU s-a putut crea: " . $e->getMessage()];
                }
            }
        }

        /* Marcăm schema ca fiind la zi, ca migrarea automată să nu
           mai încerce la fiecare accesare. */
        try {
            $s = $pdo->prepare('INSERT INTO setari (cheie, valoare) VALUES (?, ?)
                                ON DUPLICATE KEY UPDATE valoare = VALUES(valoare)');
            $s->execute(['schema_v', '6']);
            $rezultate[] = ['ok', 'Versiunea structurii însemnată ca 6'];
        } catch (Throwable $e) {
            $rezultate[] = ['rau', 'Nu s-a putut scrie versiunea structurii: ' . $e->getMessage()];
        }
    }
}

/* Verificarea finală, după eventuala rulare. */
$lipsesc = 0; $necunoscute = 0;
foreach ($pasi as $pas) {
    $st = starea_pasului($pdo, $pas);
    if ($st === null)   $necunoscute++;
    elseif ($st === 0)  $lipsesc++;
}

$culori = ['ok' => '#1B7F4E', 'sarit' => '#5A6B72', 'rau' => '#B3261E'];
?><!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="icon" href="assets/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
<title>Instalare bază de date · <?= h(NUME_MIRE) ?> &amp; <?= h(NUME_MIREASA) ?></title>
<link rel="stylesheet" href="assets/fonturi.css">
<link rel="stylesheet" href="assets/style.css">
<style>
  .lista-pasi{display:flex;flex-direction:column;gap:2px;margin-top:8px}
  .pas{display:flex;gap:12px;align-items:baseline;padding:7px 10px;border-radius:8px}
  .pas:nth-child(odd){background:#FBFAF7}
  .pas .semn{width:16px;text-align:center;font-weight:700}
  .pas .rol{color:var(--muted);font-size:.85rem;margin-left:auto;text-align:right}
  .rezultat{padding:8px 10px;border-radius:8px;font-size:.9rem;margin-bottom:3px}
  .rez-ok{background:#F1F9F4;color:#1B7F4E}
  .rez-sarit{background:#F6F5F2;color:#5A6B72}
  .rez-rau{background:#FFF6F5;color:#B3261E}
  code{background:#F0EDE7;padding:2px 6px;border-radius:5px;font-size:.86rem}
</style>
</head>
<body>
<main class="container narrow" style="padding:40px 0 60px">

  <div class="panou">
    <h2>Instalarea bazei de date</h2>
    <p class="ajutor">
      Creează tabelele, coloanele și indexurile de care are nevoie albumul.
      <strong>Nu șterge niciodată date</strong> — creează doar ce lipsește, deci poate fi
      rulat oricând, chiar și după ce invitații au încărcat poze.
    </p>

    <?php if ($eroareBd): ?>
      <div class="alerta">
        <strong>Nu există conexiune la baza de date.</strong><br>
        Verifică <code>DB_NAME</code>, <code>DB_USER</code> și <code>DB_PASS</code> din
        <code>config.php</code>. În cPanel, numele bazei și al utilizatorului încep de obicei
        cu prefixul contului.<br>
        <span class="mic">Mesajul serverului: <?= h($eroareBd) ?></span>
      </div>
    <?php else: ?>

      <?php if ($aRulat): ?>
        <h3 style="margin-top:22px">Ce s-a întâmplat</h3>
        <?php foreach ($rezultate as [$fel, $text]): ?>
          <div class="rezultat rez-<?= h($fel) ?>"><?= h($text) ?></div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($lipsesc === 0 && $necunoscute === 0): ?>
        <div class="alerta ok" style="margin-top:18px">
          <strong>✓ Totul e la locul lui.</strong>
          Baza de date are toate tabelele, coloanele și indexurile necesare.
          <?php if (!$aRulat): ?> Nu e nimic de făcut.<?php endif; ?>
        </div>
      <?php elseif ($necunoscute > 0): ?>
        <div class="alerta" style="margin-top:18px">
          <strong>Nu s-au putut verifica <?= $necunoscute ?> element(e).</strong>
          Utilizatorul bazei de date pare să nu aibă drept de citire în catalog.
          Cere-i gazdei drepturi depline pe baza <code><?= h(DB_NAME) ?></code>.
        </div>
      <?php else: ?>
        <div class="alerta" style="margin-top:18px">
          <strong><?= $lipsesc ?> element(e) lipsesc.</strong> Apasă butonul de mai jos ca să fie create.
        </div>
      <?php endif; ?>

      <h3 style="margin-top:22px">Ce trebuie să existe</h3>
      <div class="lista-pasi">
        <?php foreach ($pasi as $pas):
          [$fel, $tabela, $nume, $descriere] = $pas;
          $st = starea_pasului($pdo, $pas);
          $eticheta = eticheta_pas($fel, $tabela, $nume);
          $semn  = $st === 1 ? '✓' : ($st === null ? '?' : '✕');
          $culoare = $st === 1 ? $culori['ok'] : ($st === null ? '#B5730F' : $culori['rau']);
        ?>
          <div class="pas">
            <span class="semn" style="color:<?= $culoare ?>"><?= $semn ?></span>
            <span><?= h($eticheta) ?></span>
            <span class="rol"><?= h($descriere) ?></span>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($lipsesc > 0): ?>
        <form method="post" style="margin-top:22px"
              onsubmit="return confirm('Se creează structurile lipsă. Datele existente nu sunt atinse. Continui?')">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <button class="btn btn-primar btn-full" type="submit">
            Creează cele <?= $lipsesc ?> element(e) lipsă
          </button>
        </form>
      <?php elseif (!$aRulat): ?>
        <form method="post" style="margin-top:22px">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <button class="btn btn-ghost btn-full" type="submit">Verifică și repară oricum</button>
        </form>
      <?php endif; ?>

    <?php endif; ?>

    <p style="margin-top:24px;display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn btn-ghost btn-mic" href="admin.php">← Înapoi în panou</a>
      <a class="btn btn-ghost btn-mic" href="info.php">Diagnostic complet</a>
    </p>
  </div>

  <div class="panou">
    <h2>Întrebări firești</h2>
    <p class="ajutor" style="margin-bottom:0">
      <strong>Trebuie să am date în baza de date?</strong><br>
      Nu. Trebuie doar ca tabelele să existe, chiar goale. Zero poze înainte de nuntă
      e normal — pozele vin de la invitați.<br><br>

      <strong>Pot rula asta de mai multe ori?</strong><br>
      Da. Creează doar ce lipsește; ce există rămâne neatins, cu tot cu poze.<br><br>

      <strong>Am deja un fișier <code>setup.php</code> pe server?</strong><br>
      Șterge-l. Era din instalarea veche și nu cere autentificare, deci oricine îl putea
      deschide. Fișierul de față îl înlocuiește și e protejat prin login.
    </p>
  </div>

</main>
</body>
</html>
