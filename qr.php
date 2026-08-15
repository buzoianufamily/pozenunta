<?php
require_once __DIR__ . '/functions.php';
cere_admin();
$url = SITE_URL ?: ((($_SERVER['HTTPS'] ?? '') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . str_replace('qr.php', '', $_SERVER['REQUEST_URI'] ?? '/'));
?><!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cod QR · <?= h(NUME_MIRE) ?> &amp; <?= h(NUME_MIREASA) ?></title>
<link rel="stylesheet" href="assets/fonturi.css">
<link rel="stylesheet" href="assets/style.css">
<style>
  .qr-pagina{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:30px}
  .qr-card{background:#fff;border:1px solid var(--line);border-radius:24px;box-shadow:var(--shadow);
    width:380px;max-width:100%;text-align:center;padding:44px 36px;position:relative;overflow:hidden}
  .qr-card .eyebrow{margin-bottom:6px}
  .qr-card h1{font-family:var(--serif);font-weight:500;font-size:2.6rem;margin:.2rem 0}
  .qr-card h1 .amp{color:var(--accent);font-style:italic}
  .qr-card .dt{font-size:.8rem;letter-spacing:.28em;text-transform:uppercase;color:var(--muted);margin-bottom:24px}
  #qrcode{display:inline-block;padding:16px;background:#fff;border:1px solid var(--line);border-radius:14px}
  #qrcode img,#qrcode canvas{display:block}
  .qr-card .indemn{font-family:var(--serif);font-size:1.3rem;margin-top:22px;color:var(--ink)}
  .qr-card .web{font-size:.85rem;color:var(--accent-deep);margin-top:6px;word-break:break-all}
  .qr-actiuni{position:fixed;top:20px;right:20px;display:flex;gap:10px}
  @media print{
    .qr-actiuni{display:none}
    body{background:#fff}
    body::before{display:none}
    .qr-card{border:none;box-shadow:none}
  }
</style>
</head>
<body>
<div class="qr-actiuni">
  <a class="btn btn-ghost btn-mic" href="admin.php">← Înapoi</a>
  <button class="btn btn-primar btn-mic" onclick="window.print()">Printează</button>
</div>

<div class="qr-pagina">
  <div class="qr-card">
    <div class="ornament"><span class="ln"></span><span class="dot"></span><span class="ln r"></span></div>
    <p class="eyebrow" style="margin-top:14px">Albumul nostru de nuntă</p>
    <h1><?= h(NUME_MIRE) ?> <span class="amp">&amp;</span> <?= h(NUME_MIREASA) ?></h1>
    <div class="dt"><?= h(DATA_NUNTII) ?></div>
    <div id="qrcode"></div>
    <div class="indemn">Scanează & încarcă pozele tale 🤍</div>
    <div class="web"><?= h(preg_replace('#^https?://#', '', rtrim($url, '/'))) ?></div>
  </div>
</div>

<script src="assets/vendor/qrcode.min.js"></script>
<script>
  new QRCode(document.getElementById('qrcode'), {
    text: <?= json_encode($url) ?>,
    width: 240, height: 240,
    colorDark: '#2C2722', colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.M
  });
</script>
</body>
</html>
