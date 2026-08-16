<?php
require_once __DIR__ . '/functions.php';

function cap_pagina(string $titlu, string $paginaActiva = ''): void {
    /* Codul care recunoaște invitatul se creează AICI, la afișarea paginii,
       nu în cererile de încărcare. Motivul: fișierele pleacă mai multe
       deodată, iar dacă niciuna nu găsește cookie-ul, fiecare își face
       codul ei — și atunci invitatul rămâne cu un singur cod din trei,
       iar restul pozelor lui nu mai sunt ale nimănui.
       Aici cererea e una singură, deci codul iese unul singur.
       Trebuie apelat înainte de orice afișare, ca antetul să poată pleca. */
    jeton_invitat(true);

    $mire = h(NUME_MIRE); $mireasa = h(NUME_MIREASA);
    ?><!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#FBF7EF">
<?php /* Album privat de familie: nu-l vrem în rezultatele căutărilor.
         Previzualizarea de pe WhatsApp/Facebook funcționează în continuare,
         pentru că nu trece prin indexarea motoarelor de căutare. */ ?>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="icon" href="assets/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
<title><?= h($titlu) ?> · <?= $mire ?> &amp; <?= $mireasa ?></title>
<meta name="description" content="Albumul foto al nunții <?= $mire ?> &amp; <?= $mireasa ?> — <?= h(DATA_NUNTII) ?>. Încarcă-ți pozele și retrăiește momentele.">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= $mire ?> &amp; <?= $mireasa ?>">
<meta property="og:title" content="<?= $mire ?> &amp; <?= $mireasa ?> — Album de nuntă">
<meta property="og:description" content="<?= h(DATA_NUNTII) ?> · Încarcă-ți pozele și retrăiește momentele alături de noi.">
<meta property="og:url" content="<?= h(SITE_URL) ?>">
<?php if (are_cover()): ?>
<meta property="og:image" content="<?= h(rtrim(SITE_URL, '/') . '/' . url_cover()) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php else: ?>
<meta name="twitter:card" content="summary">
<?php endif; ?>
<link rel="stylesheet" href="assets/fonturi.css?v=<?= @filemtime(__DIR__ . '/assets/fonturi.css') ?>">
<link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?>">
<?php /* Limitele adevărate ale serverului, ca telefonul să nu urce 20 de
         minute degeaba și să afle abia la final că fișierul e prea mare. */ ?>
<script>window.NUNTA = {
  limita: <?= (int)MAX_FILE_SIZE ?>,
  limitaText: <?= json_encode(format_marime(MAX_FILE_SIZE)) ?>,
  bucata: <?= (int)dimensiune_bucata() ?>
};</script>
</head>
<body>
<header class="site-header">
  <div class="container nav">
    <a class="brand" href="/" aria-label="Acasă">
      <span class="mono"><?= mb_substr(NUME_MIRE,0,1) ?> <span class="amp">&amp;</span> <?= mb_substr(NUME_MIREASA,0,1) ?></span>
      <span class="date"><?= h(DATA_NUNTII) ?></span>
    </a>
    <nav class="nav-links">
      <a href="/" class="<?= $paginaActiva==='acasa'?'activ':'' ?>">Încarcă poze sau clipuri</a>
      <a href="galerie" class="<?= $paginaActiva==='galerie'?'activ':'' ?>">Galerie</a>
      <a href="urari" class="<?= $paginaActiva==='urari'?'activ':'' ?>">Carte de urări</a>
    </nav>
  </div>
</header>
<?php
}

function subsol_pagina(): void {
    ?>
<footer class="site-footer">
  <div class="container">
    <div class="mono"><?= h(NUME_MIRE) ?> <span class="amp">&amp;</span> <?= h(NUME_MIREASA) ?></div>
    <div class="mic"><?= h(DATA_NUNTII) ?></div>
  </div>
</footer>
<div class="toast" id="toast"></div>
<script src="assets/app.js?v=<?= @filemtime(__DIR__ . '/assets/app.js') ?>"></script>
</body>
</html>
<?php
}
