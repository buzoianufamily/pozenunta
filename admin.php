<?php
require_once __DIR__ . '/functions.php';
cere_admin();
asigura_schema();

$pdo = db();

/* ============================================================
   PROCESARE ACȚIUNI (POST)
   ============================================================ */
$ajax  = (($_POST['ajax'] ?? '') === '1') || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
$notif = null;

function raspunde_json(array $d): void { header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

function sterge_poza_dupa_id(PDO $pdo, int $id): bool {
    $s = $pdo->prepare('SELECT nume_fisier FROM poze WHERE id = ?');
    $s->execute([$id]);
    $nf = $s->fetchColumn();
    if (!$nf) return false;
    @unlink(UPLOAD_DIR . $nf);
    @unlink(THUMB_DIR . $nf . '.jpg');
    $pdo->prepare('DELETE FROM poze WHERE id = ?')->execute([$id]);
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? '')) {
        if ($ajax) raspunde_json(['ok' => false, 'eroare' => 'Sesiune expirată.']);
        $notif = ['err', 'Sesiune expirată. Reîncarcă pagina.'];
    } else {
        $actiune = $_POST['actiune'] ?? '';

        if ($actiune === 'sterge') {
            $ok = sterge_poza_dupa_id($pdo, (int)($_POST['id'] ?? 0));
            if ($ajax) raspunde_json(['ok' => $ok]);
            $notif = ['ok', 'Fotografie ștearsă.'];

        } elseif ($actiune === 'aproba') {
            $pdo->prepare('UPDATE poze SET aprobat = 1 WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
            if ($ajax) raspunde_json(['ok' => true]);
            $notif = ['ok', 'Fotografie aprobată.'];

        } elseif ($actiune === 'sterge_multe' || $actiune === 'aproba_multe') {
            $ids = $_POST['ids'] ?? [];
            if (is_string($ids)) $ids = array_filter(explode(',', $ids));
            $ids = array_map('intval', (array)$ids);
            $n = 0;
            foreach ($ids as $id) {
                if ($id <= 0) continue;
                if ($actiune === 'sterge_multe') { if (sterge_poza_dupa_id($pdo, $id)) $n++; }
                else { $pdo->prepare('UPDATE poze SET aprobat = 1 WHERE id = ?')->execute([$id]); $n++; }
            }
            if ($ajax) raspunde_json(['ok' => true, 'n' => $n]);
            $notif = ['ok', ($actiune === 'sterge_multe' ? "$n șterse." : "$n aprobate.")];

        } elseif ($actiune === 'comuta_moderare') {
            salveaza_setare('moderare', moderare_activa() ? '0' : '1');
            $notif = ['ok', 'Setare salvată.'];

        } elseif ($actiune === 'salveaza_texte') {
            /* Salvăm doar cheile pe care le cunoaștem, ca să nu ajungă
               orice câmp trimis din afară în setări. */
            $n = 0;
            foreach (texte_editabile() as $cheie => $def) {
                if (!array_key_exists($cheie, $_POST)) continue;
                salveaza_setare($cheie, mb_substr(trim((string)$_POST[$cheie]), 0, 600));
                $n++;
            }
            $notif = ['ok', "Textele au fost actualizate ($n)."];

        } elseif ($actiune === 'texte_implicite') {
            foreach (texte_editabile() as $cheie => $def) salveaza_setare($cheie, $def[2]);
            $notif = ['ok', 'Textele au revenit la varianta de pornire.'];

        } elseif ($actiune === 'salveaza_mesaj') {
            salveaza_setare('mesaj_bun_venit', trim((string)($_POST['mesaj'] ?? '')));
            $notif = ['ok', 'Mesajul de bun venit a fost actualizat.'];

        } elseif ($actiune === 'cover') {
            if (!empty($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK && is_uploaded_file($_FILES['cover']['tmp_name'])) {
                $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, extensii_imagini(), true)) {
                    $nf = 'cover_' . bin2hex(random_bytes(6)) . '.' . $ext;
                    if (@move_uploaded_file($_FILES['cover']['tmp_name'], UPLOAD_DIR . $nf)) {
                        @chmod(UPLOAD_DIR . $nf, 0644);
                        $vechi = setare('cover', '');
                        if ($vechi && is_file(UPLOAD_DIR . $vechi)) @unlink(UPLOAD_DIR . $vechi);
                        sterge_cover_mic((string)$vechi);
                        salveaza_setare('cover', $nf);
                        /* Varianta mică se face acum, cât ești tu aici, nu
                           la prima cerere a unui invitat — altfel el ar fi
                           cel care așteaptă o secundă degeaba. */
                        cover_mic();
                        $notif = ['ok', 'Fotografia de cuplu a fost setată.'];
                    } else { $notif = ['err', 'Nu s-a putut salva imaginea.']; }
                } else { $notif = ['err', 'Format de imagine neacceptat.']; }
            } else { $notif = ['err', 'Selectează o imagine.']; }

        } elseif ($actiune === 'sterge_cover') {
            $vechi = setare('cover', '');
            if ($vechi && is_file(UPLOAD_DIR . $vechi)) @unlink(UPLOAD_DIR . $vechi);
            sterge_cover_mic((string)$vechi);
            salveaza_setare('cover', '');
            $notif = ['ok', 'Fotografia de cuplu a fost ștearsă.'];

        /* ---------- urări ---------- */
        } elseif ($actiune === 'sterge_urare') {
            $pdo->prepare('DELETE FROM urari WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
            if ($ajax) raspunde_json(['ok' => true]);
            $notif = ['ok', 'Urarea a fost ștearsă.'];
        } elseif ($actiune === 'aproba_urare') {
            $pdo->prepare('UPDATE urari SET aprobat = 1 WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
            if ($ajax) raspunde_json(['ok' => true]);
            $notif = ['ok', 'Urarea a fost aprobată.'];
        }
    }
}

/* ============================================================
   STATISTICI
   ============================================================ */
$total     = (int)$pdo->query('SELECT COUNT(*) FROM poze')->fetchColumn();
$nrImagini = (int)$pdo->query("SELECT COUNT(*) FROM poze WHERE tip='imagine'")->fetchColumn();
$nrVideo   = (int)$pdo->query("SELECT COUNT(*) FROM poze WHERE tip='video'")->fetchColumn();
$nrInvitati= (int)$pdo->query("SELECT COUNT(DISTINCT nume_invitat) FROM poze WHERE nume_invitat IS NOT NULL AND nume_invitat <> ''")->fetchColumn();
$spatiu    = (int)$pdo->query('SELECT COALESCE(SUM(marime),0) FROM poze')->fetchColumn();
$nrAstept  = (int)$pdo->query('SELECT COUNT(*) FROM poze WHERE aprobat=0')->fetchColumn();

/* Urările: cele neaprobate primele, ca să sară în ochi. */
$urariAdmin = []; $nrUrari = 0; $nrUrariAstept = 0;
try {
    $nrUrari       = (int)$pdo->query('SELECT COUNT(*) FROM urari')->fetchColumn();
    $nrUrariAstept = (int)$pdo->query('SELECT COUNT(*) FROM urari WHERE aprobat=0')->fetchColumn();
    $urariAdmin    = $pdo->query(
        'SELECT id, nume, mesaj, aprobat, data_creare FROM urari
         ORDER BY aprobat ASC, data_creare DESC, id DESC LIMIT 200'
    )->fetchAll();
} catch (Throwable $e) { /* tabela poate lipsi la prima rulare */ }
$quotaBytes  = DISK_QUOTA_GB * 1024 * 1024 * 1024;
$procentDisc = $quotaBytes > 0 ? min(100, (int)round($spatiu / $quotaBytes * 100)) : 0;
$liberBytes  = max(0, $quotaBytes - $spatiu);
$culoareDisc = $procentDisc < 70 ? 'var(--ok)' : ($procentDisc < 90 ? '#b58a2b' : 'var(--err)');

/* ============================================================
   GALERIE ADMIN (paginare + filtru)
   ============================================================ */
$filtru = ($_GET['f'] ?? 'toate') === 'asteptare' ? 'asteptare' : 'toate';
$q      = trim((string)($_GET['q'] ?? ''));
$perPag = 48;
$pag    = max(1, (int)($_GET['p'] ?? 1));
$off    = ($pag - 1) * $perPag;

$conditii = [];
if ($filtru === 'asteptare') $conditii[] = 'aprobat = 0';
if ($q !== '')               $conditii[] = 'nume_invitat LIKE :q';
$where = $conditii ? ('WHERE ' . implode(' AND ', $conditii)) : '';

$cst = $pdo->prepare("SELECT COUNT(*) FROM poze $where");
if ($q !== '') $cst->bindValue(':q', '%' . $q . '%');
$cst->execute();
$totalFiltrat = (int)$cst->fetchColumn();
$totalPagini  = max(1, (int)ceil($totalFiltrat / $perPag));

$stmt = $pdo->prepare("SELECT * FROM poze $where ORDER BY data_incarcare DESC, id DESC LIMIT :l OFFSET :o");
$stmt->bindValue(':l', $perPag, PDO::PARAM_INT);
$stmt->bindValue(':o', $off, PDO::PARAM_INT);
if ($q !== '') $stmt->bindValue(':q', '%' . $q . '%');
$stmt->execute();
$poze = $stmt->fetchAll();
?><!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="icon" href="assets/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
<title>Panou administrare · <?= h(NUME_MIRE) ?> &amp; <?= h(NUME_MIREASA) ?></title>
<link rel="stylesheet" href="assets/fonturi.css">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-header admin-top">
  <div class="container nav">
    <div class="brand">
      <span class="mono"><?= mb_substr(NUME_MIRE,0,1) ?> <span class="amp">&amp;</span> <?= mb_substr(NUME_MIREASA,0,1) ?></span>
      <span class="date" style="color:#9c8f78">Administrare</span>
    </div>
    <nav class="nav-links">
      <a href="/" target="_blank">Pagina de încărcare</a>
      <a href="galerie" target="_blank">Galerie publică</a>
      <a href="logout.php">Ieșire (<?= h($_SESSION['admin_user'] ?? 'admin') ?>)</a>
    </nav>
  </div>
</header>

<main class="admin-main">
  <div class="container">

    <?php if ($notif): ?>
      <div class="alerta <?= $notif[0]==='ok'?'ok':'' ?>" style="margin-bottom:22px"><?= h($notif[1]) ?></div>
    <?php endif; ?>

    <!-- STATISTICI -->
    <div class="statistici">
      <div class="stat"><div class="nr"><?= $total ?></div><div class="et">Fotografii & filme</div></div>
      <div class="stat"><div class="nr"><?= $nrInvitati ?></div><div class="et">Invitați care au semnat</div></div>
      <div class="stat"><div class="nr"><?= h(format_marime($spatiu)) ?></div><div class="et">Spațiu folosit</div></div>
      <div class="stat"><div class="nr" style="color:<?= $nrAstept? '#b58a2b':'var(--accent-deep)' ?>"><?= $nrAstept ?></div><div class="et">În așteptare</div></div>
    </div>

    <!-- SPAȚIU PE DISC -->
    <div class="panou disc-panou">
      <div class="disc-cap">
        <span><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" style="vertical-align:-3px"><rect x="3" y="4" width="18" height="6" rx="1.5"/><rect x="3" y="14" width="18" height="6" rx="1.5"/><circle cx="6.6" cy="7" r=".7" fill="currentColor" stroke="none"/><circle cx="6.6" cy="17" r=".7" fill="currentColor" stroke="none"/></svg> Spațiu pe disc</span>
        <span class="disc-cifre"><?= h(format_marime($spatiu)) ?> din <?= DISK_QUOTA_GB ?> GB folosiți · <strong><?= h(format_marime($liberBytes)) ?> liber</strong></span>
      </div>
      <div class="disc-bara"><i style="width:<?= $procentDisc ?>%;background:<?= $culoareDisc ?>"></i></div>
      <?php if ($procentDisc >= 90): ?>
        <p class="ajutor" style="color:var(--err);margin-top:10px">Discul e aproape plin. Cumpără spațiu suplimentar din cPanel/RoMarg, apoi mărește <code>DISK_QUOTA_GB</code> în <code>config.php</code>.</p>
      <?php elseif ($procentDisc >= 70): ?>
        <p class="ajutor" style="margin-top:10px">Ai folosit <?= $procentDisc ?>% din spațiu. Mai ai loc, dar urmărește filmele — ele ocupă cel mai mult.</p>
      <?php endif; ?>
    </div>

    <!-- SETĂRI -->
    <div class="panou">
      <h2>Setări album</h2>
      <p class="ajutor">Imagini: <?= $nrImagini ?> · Filme: <?= $nrVideo ?></p>

      <div class="rand-form" style="justify-content:space-between;border-bottom:1px solid var(--line-soft);padding-bottom:18px;margin-bottom:18px">
        <div>
          <strong>Moderare poze</strong>
          <div class="ajutor" style="margin:2px 0 0">Când e activă, pozele apar în galerie doar după ce le aprobi tu. Acum: <strong><?= moderare_activa() ? 'ACTIVĂ' : 'dezactivată' ?></strong>.</div>
        </div>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="actiune" value="comuta_moderare">
          <label class="switch">
            <input type="checkbox" onchange="this.form.submit()" <?= moderare_activa()?'checked':'' ?>>
            <span class="pin"></span>
          </label>
        </form>
      </div>

      <form method="post" style="border-bottom:1px solid var(--line-soft);padding-bottom:18px;margin-bottom:18px">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="actiune" value="salveaza_mesaj">
        <div class="camp">
          <label>Mesajul de bun venit (apare pe prima pagină)</label>
          <textarea name="mesaj" rows="5"><?= h(mesaj_bun_venit()) ?></textarea>
        </div>
        <button class="btn btn-primar btn-mic" type="submit">Salvează mesajul</button>
      </form>

      <div class="rand-form" style="align-items:flex-end;gap:18px;flex-wrap:wrap">
        <form method="post" enctype="multipart/form-data" class="rand-form" style="align-items:flex-end;gap:10px">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="actiune" value="cover">
          <div class="camp" style="margin:0">
            <label>Fotografia de cuplu (apare pe prima pagină)</label>
            <input type="file" name="cover" accept="image/*" required>
          </div>
          <button class="btn btn-ghost btn-mic" type="submit">Încarcă coperta</button>
        </form>
        <?php if (are_cover()): ?>
          <div class="rand-form" style="align-items:center;gap:10px">
            <img src="<?= h(url_cover()) ?>" alt="" style="width:64px;height:48px;object-fit:cover;border-radius:8px;border:1px solid var(--line-soft)">
            <form method="post" onsubmit="return confirm('Ștergi fotografia de cuplu?')">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="actiune" value="sterge_cover">
              <button class="btn btn-ghost btn-mic" type="submit">Șterge coperta</button>
            </form>
          </div>
        <?php endif; ?>
        <div style="flex:1"></div>
        <a class="btn btn-ghost btn-mic" href="qr.php" target="_blank">Cod QR pentru invitați</a>
        <a class="btn btn-dark btn-mic" href="descarca-zip.php">Descarcă tot (ZIP)</a>
      </div>
    </div>

    <!-- TEXTELE DE PE PAGINI -->
    <div class="panou">
      <h2>Textele de pe pagini</h2>
      <p class="ajutor">
        Tot ce citește invitatul, în afară de mesajul de bun venit (care are câmpul lui mai sus).
        Lasă un câmp gol ca să revină la textul de pornire.
      </p>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="actiune" value="salveaza_texte">

        <?php
        /* Grupăm după pagină, ca să se găsească ușor. */
        $peGrupe = [];
        foreach (texte_editabile() as $cheie => $def) {
            [$grup, $eticheta, $implicit, $lung] = $def;
            $peGrupe[$grup][$cheie] = [$eticheta, $implicit, $lung];
        }
        foreach ($peGrupe as $grup => $campuri): ?>
          <h3 style="font-family:var(--serif);font-weight:500;font-size:1.1rem;color:var(--green);
                     margin:22px 0 10px;padding-bottom:6px;border-bottom:1px solid var(--line-soft)">
            <?= h($grup) ?>
          </h3>
          <?php foreach ($campuri as $cheie => [$eticheta, $implicit, $lung]): ?>
            <div class="camp" style="margin-bottom:14px">
              <label for="<?= h($cheie) ?>"><?= h($eticheta) ?></label>
              <?php if ($lung): ?>
                <textarea id="<?= h($cheie) ?>" name="<?= h($cheie) ?>" rows="2"><?= h(text($cheie)) ?></textarea>
              <?php else: ?>
                <input type="text" id="<?= h($cheie) ?>" name="<?= h($cheie) ?>" maxlength="600" value="<?= h(text($cheie)) ?>">
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endforeach; ?>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px">
          <button class="btn btn-primar btn-mic" type="submit">Salvează textele</button>
        </div>
      </form>

      <form method="post" style="margin-top:12px"
            onsubmit="return confirm('Toate textele revin la varianta de pornire. Continui?')">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="actiune" value="texte_implicite">
        <button class="btn btn-ghost btn-mic" type="submit">Revino la textele de pornire</button>
      </form>
    </div>

    <!-- CARTE DE URĂRI -->
    <div class="panou">
      <h2>Carte de urări</h2>
      <p class="ajutor">
        <?= $nrUrari ?> <?= $nrUrari === 1 ? 'urare' : 'urări' ?>
        <?php if ($nrUrariAstept > 0): ?>
          · <strong><?= $nrUrariAstept ?> în așteptare</strong>
        <?php endif; ?>
        <?php if ($nrUrari > 200): ?> · se arată cele mai recente 200<?php endif; ?>
      </p>

      <?php if (empty($urariAdmin)): ?>
        <p class="ajutor">Nicio urare încă.</p>
      <?php else: ?>
        <div class="urari-admin">
          <?php foreach ($urariAdmin as $u): ?>
            <div class="urare-rand<?= $u['aprobat'] ? '' : ' neaprobata' ?>" data-urare="<?= (int)$u['id'] ?>">
              <div class="ur-continut">
                <div class="ur-cap">
                  <strong><?= h($u['nume']) ?></strong>
                  <span class="ur-data"><?= date('d.m.Y H:i', strtotime($u['data_creare'])) ?></span>
                  <?php if (!$u['aprobat']): ?><span class="ur-eticheta">în așteptare</span><?php endif; ?>
                </div>
                <div class="ur-mesaj"><?= nl2br(h($u['mesaj'])) ?></div>
              </div>
              <div class="ur-actiuni">
                <?php if (!$u['aprobat']): ?>
                  <button class="btn btn-mic btn-primar" data-aproba-urare="<?= (int)$u['id'] ?>">Aprobă</button>
                <?php endif; ?>
                <button class="btn btn-mic btn-ghost" data-sterge-urare="<?= (int)$u['id'] ?>">Șterge</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- GALERIE ADMIN -->
    <div class="panou">
      <h2>Toate fotografiile</h2>
      <p class="ajutor"><?= $totalFiltrat ?> rezultate · pagina <?= $pag ?> din <?= $totalPagini ?></p>

      <form method="get" class="cauta-form">
        <input type="hidden" name="f" value="<?= h($filtru) ?>">
        <input type="search" name="q" value="<?= h($q) ?>" placeholder="Caută după numele invitatului…">
        <button class="btn btn-mic btn-ghost" type="submit">Caută</button>
        <?php if ($q !== ''): ?><a class="btn btn-mic btn-ghost" href="?f=<?= h($filtru) ?>">Renunță</a><?php endif; ?>
      </form>

      <div class="bara-selectie">
        <a class="btn btn-mic <?= $filtru==='toate'?'btn-primar':'btn-ghost' ?>" href="?f=toate<?= $q!==''?'&q='.urlencode($q):'' ?>">Toate (<?= $total ?>)</a>
        <a class="btn btn-mic <?= $filtru==='asteptare'?'btn-primar':'btn-ghost' ?>" href="?f=asteptare<?= $q!==''?'&q='.urlencode($q):'' ?>">În așteptare (<?= $nrAstept ?>)</a>
        <div style="flex:1"></div>
        <label class="switch" style="font-size:.85rem"><input type="checkbox" id="sel-tot"><span class="pin"></span> Selectează tot</label>
        <span class="sel-info" id="sel-info"></span>
        <button class="btn btn-mic btn-ghost" id="btn-aproba-sel" style="display:none">Aprobă selectate</button>
        <button class="btn btn-mic" id="btn-sterge-sel" style="display:none;background:var(--err);color:#fff">Șterge selectate</button>
      </div>

      <?php if (empty($poze)): ?>
        <div class="gol" style="font-size:1.2rem;padding:40px">Nu există fotografii aici încă.</div>
      <?php else: ?>
        <div class="admin-galerie" id="admin-galerie">
          <?php foreach ($poze as $p): ?>
            <?php $prev = url_previzualizare($p); $orig = url_original($p); ?>
            <div class="admin-poza" data-id="<?= (int)$p['id'] ?>">
              <a href="<?= h($orig) ?>" target="_blank">
                <?php if ($p['tip'] === 'video'): ?>
                  <video src="<?= h($orig) ?>#t=0.1" preload="metadata" muted></video>
                  <span class="badge video">video</span>
                <?php else: ?>
                  <img loading="lazy" src="<?= h($prev) ?>" alt="">
                <?php endif; ?>
              </a>
              <?php if ((int)$p['aprobat'] === 0): ?><span class="badge asteptare" style="<?= $p['tip']==='video'?'top:34px':'' ?>">în așteptare</span><?php endif; ?>
              <label class="poz-check" style="position:absolute;top:8px;left:8px;z-index:3;<?= ($p['tip']==='video'||(int)$p['aprobat']===0)?'top:auto;bottom:8px':'' ?>">
                <input type="checkbox" class="chk" value="<?= (int)$p['id'] ?>">
              </label>
              <div class="actiuni">
                <?php if ((int)$p['aprobat'] === 0): ?>
                  <button class="ic aproba" title="Aprobă" data-aproba="<?= (int)$p['id'] ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M20 6L9 17l-5-5"/></svg>
                  </button>
                <?php endif; ?>
                <button class="ic sterge" title="Șterge" data-sterge="<?= (int)$p['id'] ?>">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
                </button>
              </div>
              <?php if (!empty($p['nume_invitat']) || !empty($p['mesaj'])): ?>
                <div class="info">
                  <?php if (!empty($p['nume_invitat'])): ?><strong><?= h($p['nume_invitat']) ?></strong><br><?php endif; ?>
                  <?= h(mb_strimwidth((string)$p['mesaj'], 0, 60, '…')) ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if ($totalPagini > 1): ?>
          <div style="display:flex;gap:8px;justify-content:center;margin-top:24px;flex-wrap:wrap">
            <?php for ($i = 1; $i <= $totalPagini; $i++): ?>
              <a class="btn btn-mic <?= $i===$pag?'btn-primar':'btn-ghost' ?>" href="?f=<?= h($filtru) ?>&p=<?= $i ?><?= $q!==''?'&q='.urlencode($q):'' ?>"><?= $i ?></a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

  </div>
</main>

<div class="toast" id="toast"></div>

<form id="form-actiuni" method="post" style="display:none">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="actiune" id="fa-actiune">
  <input type="hidden" name="ids" id="fa-ids">
</form>

<script>
(function () {
  var CSRF = <?= json_encode(csrf_token()) ?>;
  var toastEl = document.getElementById('toast'), tt;
  function toast(m){ toastEl.textContent=m; toastEl.classList.add('aratat'); clearTimeout(tt); tt=setTimeout(function(){toastEl.classList.remove('aratat')},2600); }

  function actiuneSingulara(actiune, id, callback){
    var fd = new FormData();
    fd.append('csrf', CSRF); fd.append('ajax','1'); fd.append('actiune', actiune); fd.append('id', id);
    fetch('admin.php', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
      .then(function(r){return r.json();}).then(function(d){ callback(d && d.ok); })
      .catch(function(){ callback(false); });
  }

  // urări: aprobare și ștergere
  document.querySelectorAll('[data-aproba-urare]').forEach(function(b){
    b.addEventListener('click', function(){
      var id = b.getAttribute('data-aproba-urare');
      b.disabled = true;
      actiuneSingulara('aproba_urare', id, function(ok){
        if (!ok) { b.disabled = false; toast('Nu s-a putut aproba.'); return; }
        var rand = document.querySelector('[data-urare="' + id + '"]');
        if (rand) {
          rand.classList.remove('neaprobata');
          var et = rand.querySelector('.ur-eticheta'); if (et) et.remove();
          b.remove();
        }
        toast('Urarea a fost aprobată.');
      });
    });
  });
  document.querySelectorAll('[data-sterge-urare]').forEach(function(b){
    b.addEventListener('click', function(){
      if (!confirm('Sigur ștergi această urare? Acțiunea nu poate fi anulată.')) return;
      var id = b.getAttribute('data-sterge-urare');
      b.disabled = true;
      actiuneSingulara('sterge_urare', id, function(ok){
        if (!ok) { b.disabled = false; toast('Nu s-a putut șterge.'); return; }
        var rand = document.querySelector('[data-urare="' + id + '"]');
        if (rand) rand.remove();
        toast('Urarea a fost ștearsă.');
      });
    });
  });

  // ștergere individuală
  document.querySelectorAll('[data-sterge]').forEach(function(b){
    b.addEventListener('click', function(e){
      e.preventDefault(); e.stopPropagation();
      if (!confirm('Sigur ștergi această fotografie? Acțiunea nu poate fi anulată.')) return;
      var id = b.getAttribute('data-sterge');
      actiuneSingulara('sterge', id, function(ok){
        if (ok){ var t=b.closest('.admin-poza'); t.style.transition='.3s'; t.style.opacity='0'; t.style.transform='scale(.9)'; setTimeout(function(){t.remove();},300); toast('Ștearsă.'); }
        else toast('Eroare la ștergere.');
      });
    });
  });
  // aprobare individuală
  document.querySelectorAll('[data-aproba]').forEach(function(b){
    b.addEventListener('click', function(e){
      e.preventDefault(); e.stopPropagation();
      var id = b.getAttribute('data-aproba');
      actiuneSingulara('aproba', id, function(ok){
        if (ok){ var t=b.closest('.admin-poza'); var bd=t.querySelector('.badge.asteptare'); if(bd) bd.remove(); b.remove(); toast('Aprobată.'); }
        else toast('Eroare.');
      });
    });
  });

  // selecție multiplă
  var checks = Array.prototype.slice.call(document.querySelectorAll('.chk'));
  var selTot = document.getElementById('sel-tot');
  var info = document.getElementById('sel-info');
  var btnSterge = document.getElementById('btn-sterge-sel');
  var btnAproba = document.getElementById('btn-aproba-sel');

  function refresh(){
    var sel = checks.filter(function(c){return c.checked;});
    info.textContent = sel.length ? sel.length + ' selectate' : '';
    btnSterge.style.display = sel.length ? '' : 'none';
    btnAproba.style.display = sel.length ? '' : 'none';
    return sel;
  }
  checks.forEach(function(c){ c.addEventListener('change', refresh); });
  if (selTot) selTot.addEventListener('change', function(){ checks.forEach(function(c){ c.checked = selTot.checked; }); refresh(); });

  function trimiteMultiple(actiune){
    var ids = checks.filter(function(c){return c.checked;}).map(function(c){return c.value;});
    if (!ids.length) return;
    document.getElementById('fa-actiune').value = actiune;
    document.getElementById('fa-ids').value = ids.join(',');
    document.getElementById('form-actiuni').submit();
  }
  if (btnSterge) btnSterge.addEventListener('click', function(){ if (confirm('Ștergi definitiv ' + refresh().length + ' fotografii?')) trimiteMultiple('sterge_multe'); });
  if (btnAproba) btnAproba.addEventListener('click', function(){ trimiteMultiple('aproba_multe'); });
})();
</script>
</body>
</html>
