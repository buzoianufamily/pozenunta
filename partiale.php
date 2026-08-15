<?php
require_once __DIR__ . '/functions.php';

function cap_pagina(string $titlu, string $paginaActiva = ''): void {
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
</head>
<body>
<header class="site-header">
  <div class="container nav">
    <a class="brand" href="/" aria-label="Acasă">
      <span class="mono"><?= mb_substr(NUME_MIRE,0,1) ?> <span class="amp">&amp;</span> <?= mb_substr(NUME_MIREASA,0,1) ?></span>
      <span class="date"><?= h(DATA_NUNTII) ?></span>
    </a>
    <nav class="nav-links">
      <a href="index.php" class="<?= $paginaActiva==='acasa'?'activ':'' ?>">Încarcă poze sau clipuri</a>
      <a href="galerie.php" class="<?= $paginaActiva==='galerie'?'activ':'' ?>">Galerie</a>
      <a href="urari.php" class="<?= $paginaActiva==='urari'?'activ':'' ?>">Carte de urări</a>
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
