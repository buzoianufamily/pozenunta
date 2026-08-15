<?php
require_once __DIR__ . '/partiale.php';
asigura_schema();
$total = 0;
try { $total = (int)db()->query('SELECT COUNT(*) FROM poze WHERE aprobat = 1')->fetchColumn(); } catch (Throwable $e) {}
cap_pagina('Galerie', 'galerie');
?>

<section class="hero" style="padding:54px 0 18px">
  <div class="container narrow hero-inner">
    <div class="ornament fade-up d1"><span class="ln"></span><span class="dot"></span><span class="ln r"></span></div>
    <p class="eyebrow fade-up d1" style="margin-top:14px">Amintirile noastre</p>
    <h1 class="fade-up d2" style="font-size:clamp(2.4rem,6vw,4rem)">Galeria nunții</h1>
    <div class="sub-date fade-up d2" id="numar-poze"><?= $total ?> momente surprinse de voi</div>
  </div>
</section>

<section class="sectiune" style="padding-top:10px">
  <div class="container">
    <div class="galerie-bar">
      <button class="chip activ" data-sort="noi">Cele mai noi</button>
      <button class="chip" data-sort="apreciate">Cele mai apreciate</button>
    </div>

    <div class="galerie" id="galerie"></div>
    <div class="incarcare-mini" id="incarcare-mini">Se încarcă…</div>
    <div class="sentinela" id="sentinela"></div>
    <div class="gol" id="gol" style="display:none">
      Albumul așteaptă primele voastre fotografii. <br>
      <a class="btn btn-primar" style="margin-top:18px" href="index.php">Încarcă o poză sau video</a>
    </div>
  </div>
</section>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" aria-hidden="true">
  <button class="lb-inchide" id="lb-inchide" aria-label="Închide">&times;</button>
  <div class="lb-actiune">
    <button class="lb-like" id="lb-like" aria-label="Apreciază">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
      <span id="lb-like-n">0</span>
    </button>
    <a class="lb-link" id="lb-download" download>Descarcă</a>
    <!-- apare doar la fișierele încărcate de pe acest telefon -->
    <button class="lb-link lb-sterge" id="lb-sterge" hidden>Șterge</button>
  </div>
  <button class="lb-btn lb-prev" id="lb-prev" aria-label="Înapoi">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
  </button>
  <button class="lb-btn lb-next" id="lb-next" aria-label="Înainte">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
  </button>
  <div id="lb-continut"></div>
  <div class="lb-caption" id="lb-caption"></div>
</div>

<?php subsol_pagina(); ?>
