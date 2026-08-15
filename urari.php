<?php
require_once __DIR__ . '/partiale.php';
asigura_schema();

$eroare = '';
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
            $st = db()->prepare('INSERT INTO urari (nume, mesaj, aprobat, ip) VALUES (?, ?, 1, ?)');
            $st->execute([mb_substr($nume, 0, 120), mb_substr($mesaj, 0, 2000), $_SERVER['REMOTE_ADDR'] ?? null]);
            header('Location: urari.php?trimis=1'); exit; // PRG: evită retrimiterea la refresh
        } catch (Throwable $e) {
            $eroare = 'A apărut o eroare. Te rugăm să mai încerci o dată.';
        }
    }
}

$urari = [];
try {
    $urari = db()->query("SELECT nume, mesaj, data_creare FROM urari WHERE aprobat = 1 ORDER BY data_creare DESC, id DESC")->fetchAll();
} catch (Throwable $e) {}

cap_pagina('Carte de urări', 'urari');
?>

<section class="hero" style="padding:54px 0 18px">
  <div class="container narrow hero-inner">
    <div class="ornament fade-up d1"><span class="ln"></span><span class="dot"></span><span class="ln r"></span></div>
    <p class="eyebrow fade-up d1" style="margin-top:14px">Gândurile voastre</p>
    <h1 class="fade-up d2" style="font-size:clamp(2.4rem,6vw,4rem)">Carte de urări</h1>
    <div class="sub-date fade-up d2"><?= count($urari) ?> mesaje pline de drag</div>
  </div>
</section>

<div class="container narrow">
  <?php if (isset($_GET['trimis'])): ?>
    <div class="alerta ok">Îți mulțumim din suflet pentru urare! 🤍</div>
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
        <?php foreach ($urari as $u): ?>
          <figure class="urare-card fade-up">
            <blockquote class="urare-text">„<?= h($u['mesaj']) ?>”</blockquote>
            <figcaption class="urare-semn">
              <?= h($u['nume']) ?>
              <span class="urare-data"><?= date('d.m.Y', strtotime($u['data_creare'])) ?></span>
            </figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php subsol_pagina(); ?>