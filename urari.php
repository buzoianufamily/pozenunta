<?php
require_once __DIR__ . '/partiale.php';
asigura_schema();

$eroare = '';

/* ---------- invitatul își șterge propria urare ----------
   Merge fără JavaScript, ca restul paginii. Dovada de proprietate e
   același cookie secret ca la poze; serverul verifică, nu pagina. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['actiune'] ?? '') === 'sterge_urare') {
    $idU = (int)($_POST['id'] ?? 0);
    $a   = amprenta_jeton(jeton_invitat(false));
    if ($idU > 0 && ($a !== null || este_admin())) {
        try {
            $st = db()->prepare('SELECT jeton FROM urari WHERE id = ?');
            $st->execute([$idU]);
            $j = $st->fetchColumn();
            $alMeu = $j !== false && !empty($j) && $a !== null && hash_equals((string)$j, $a);
            if ($alMeu || este_admin()) {
                db()->prepare('DELETE FROM urari WHERE id = ?')->execute([$idU]);
            }
        } catch (Throwable $e) { /* ștergerea nu e critică */ }
    }
    header('Location: urari.php?sters=1'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nume    = trim((string)($_POST['nume']  ?? ''));
    $mesaj   = trim((string)($_POST['mesaj'] ?? ''));
    $capcana = trim((string)($_POST['site']  ?? '')); // honeypot anti-spam

    if ($capcana !== '') {
        header('Location: urari.php?trimis=1'); exit; // probabil bot — ignorăm discret
    }
    if ($nume === '' || $mesaj === '') {
        $eroare = 'Te rugăm să completezi și numele, și urarea.';
    } else {
        try {
            /* Urările ascultă de același comutator de moderare ca pozele. */
            $aprobat = moderare_activa() ? 0 : 1;
            $st = db()->prepare('INSERT INTO urari (nume, mesaj, aprobat, ip, jeton) VALUES (?, ?, ?, ?, ?)');
            $st->execute([
                mb_substr($nume, 0, 120),
                mb_substr($mesaj, 0, 2000),
                $aprobat,
                $_SERVER['REMOTE_ADDR'] ?? null,
                amprenta_jeton(jeton_invitat(true)),
            ]);
            header('Location: urari.php?trimis=' . ($aprobat ? '1' : '2')); exit; // PRG: evită retrimiterea la refresh
        } catch (Throwable $e) {
            $eroare = 'A apărut o eroare. Te rugăm să mai încerci o dată.';
        }
    }
}

/* ---------- listă cu paginare ---------- */
define('URARI_PE_PAGINA', 24);
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$offset = ($pagina - 1) * URARI_PE_PAGINA;

$total = 0; $urari = []; $amprentaMea = amprenta_jeton(jeton_invitat(false));
try {
    $total = (int)db()->query('SELECT COUNT(*) FROM urari WHERE aprobat = 1')->fetchColumn();
    $st = db()->prepare(
        'SELECT id, nume, mesaj, data_creare, jeton FROM urari
         WHERE aprobat = 1 ORDER BY data_creare DESC, id DESC LIMIT :l OFFSET :o'
    );
    $st->bindValue(':l', URARI_PE_PAGINA, PDO::PARAM_INT);
    $st->bindValue(':o', $offset, PDO::PARAM_INT);
    $st->execute();
    $urari = $st->fetchAll();
} catch (Throwable $e) {}

$totalPagini = max(1, (int)ceil($total / URARI_PE_PAGINA));

cap_pagina('Carte de urări', 'urari');
?>

<section class="hero" style="padding:54px 0 18px">
  <div class="container narrow hero-inner">
    <div class="ornament fade-up d1"><span class="ln"></span><span class="dot"></span><span class="ln r"></span></div>
    <p class="eyebrow fade-up d1" style="margin-top:14px">Gândurile voastre</p>
    <h1 class="fade-up d2" style="font-size:clamp(2.4rem,6vw,4rem)">Carte de urări</h1>
    <div class="sub-date fade-up d2"><?= $total ?> <?= $total === 1 ? 'mesaj plin de drag' : 'mesaje pline de drag' ?></div>
  </div>
</section>

<div class="container narrow">
  <?php if (($_GET['trimis'] ?? '') === '1'): ?>
    <div class="alerta ok">Îți mulțumim din suflet pentru urare! 🤍</div>
  <?php elseif (($_GET['trimis'] ?? '') === '2'): ?>
    <div class="alerta ok">Îți mulțumim! Urarea ta va apărea după ce o văd mirii. 🤍</div>
  <?php endif; ?>
  <?php if (isset($_GET['sters'])): ?>
    <div class="alerta ok">Urarea a fost ștearsă.</div>
  <?php endif; ?>
  <?php if ($eroare): ?>
    <div class="alerta"><?= h($eroare) ?></div>
  <?php endif; ?>

  <form method="post" class="card" style="padding:26px 28px;margin-top:6px">
    <input type="text" name="site" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
    <div class="campuri">
      <div class="camp">
        <label for="nume">Numele tău *</label>
        <input type="text" id="nume" name="nume" maxlength="120" required placeholder="ex: Familia Popescu">
      </div>
      <div class="camp">
        <label for="mesaj">Urarea ta *</label>
        <textarea id="mesaj" name="mesaj" maxlength="2000" required placeholder="Casă de piatră! Vă dorim o viață plină de iubire și fericire..."></textarea>
      </div>
    </div>
    <div style="margin-top:18px;text-align:center">
      <button class="btn btn-primar btn-full" type="submit">Trimite urarea</button>
    </div>
  </form>
</div>

<section class="sectiune" style="padding-top:30px">
  <div class="container">
    <?php if (empty($urari)): ?>
      <div class="gol">Fii primul care lasă o urare frumoasă pentru miri.</div>
    <?php else: ?>
      <div class="urari-grid">
        <?php foreach ($urari as $u):
          $alMeu = $amprentaMea !== null && !empty($u['jeton']) && hash_equals((string)$u['jeton'], $amprentaMea);
        ?>
          <figure class="urare-card fade-up">
            <blockquote class="urare-text">„<?= h($u['mesaj']) ?>”</blockquote>
            <figcaption class="urare-semn">
              <?= h($u['nume']) ?>
              <span class="urare-data"><?= date('d.m.Y', strtotime($u['data_creare'])) ?></span>
            </figcaption>
            <?php if ($alMeu || este_admin()): ?>
              <form method="post" class="urare-sterge"
                    onsubmit="return confirm('Ștergi urarea ta? Această acțiune nu poate fi anulată.')">
                <input type="hidden" name="actiune" value="sterge_urare">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button type="submit">Șterge urarea mea</button>
              </form>
            <?php endif; ?>
          </figure>
        <?php endforeach; ?>
      </div>

      <?php if ($totalPagini > 1): ?>
        <nav class="paginare" aria-label="Paginare urări">
          <?php if ($pagina > 1): ?>
            <a class="btn btn-ghost btn-mic" href="?pagina=<?= $pagina - 1 ?>">← Mai noi</a>
          <?php endif; ?>
          <span class="paginare-info">Pagina <?= $pagina ?> din <?= $totalPagini ?></span>
          <?php if ($pagina < $totalPagini): ?>
            <a class="btn btn-ghost btn-mic" href="?pagina=<?= $pagina + 1 ?>">Mai vechi →</a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<?php subsol_pagina(); ?>