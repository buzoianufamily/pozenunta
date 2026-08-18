<?php
require_once __DIR__ . '/partiale.php';
asigura_schema();

$pozeRecente  = [];
$urariRecente = [];
/* Fâșia de pe prima pagină vrea 9 momente. Cerem mai multe și le punem
   întâi pe cele care au miniatură: un film fără miniatură se afișează
   arătând chiar filmul, iar filmul are zeci de MB. Un singur asemenea
   fișier ține prima pagină în „se încarcă" pe date mobile. Aproape toate
   au miniatură (o face telefonul la trimitere), deci de obicei fâșia iese
   la fel — doar că fără surpriza aceea. */
try {
    $candidati = db()->query("SELECT * FROM poze WHERE aprobat = 1 ORDER BY data_incarcare DESC, id DESC LIMIT 30")->fetchAll();
    $cuMiniatura = []; $faraMiniatura = [];
    foreach ($candidati as $c) {
        if (are_miniatura($c)) $cuMiniatura[] = $c; else $faraMiniatura[] = $c;
    }
    $pozeRecente = array_slice(array_merge($cuMiniatura, $faraMiniatura), 0, 9);
} catch (Throwable $e) {}
try { $urariRecente = db()->query("SELECT nume, mesaj, data_creare FROM urari WHERE aprobat = 1 ORDER BY data_creare DESC, id DESC LIMIT 6")->fetchAll(); } catch (Throwable $e) {}

cap_pagina('Acasă', 'acasa');
?>

<section class="hero">
  <div class="watermark" aria-hidden="true"><?= mb_substr(NUME_MIRE,0,1) ?>&amp;<?= mb_substr(NUME_MIREASA,0,1) ?></div>
  <div class="container narrow hero-inner">
    <div class="ornament fade-up d1"><span class="ln"></span><span class="dot"></span><span class="ln r"></span></div>
    <p class="eyebrow fade-up d1" style="margin-top:14px"><?= h(text('tx_acasa_eyebrow')) ?></p>
    <h1 class="fade-up d2"><?= h(NUME_MIRE) ?> <span class="amp">&amp;</span> <?= h(NUME_MIREASA) ?></h1>
    <div class="sub-date fade-up d2"><?= h(DATA_NUNTII) ?></div>
    <?php if (are_cover()): ?>
      <div class="cover-foto fade-up d3"><img src="<?= h(url_cover()) ?>" width="1200" height="900" decoding="async" fetchpriority="high" alt="<?= h(NUME_MIRE) ?> &amp; <?= h(NUME_MIREASA) ?>"></div>
    <?php endif; ?>
  </div>
</section>

<div class="container narrow">
  <div class="card mesaj-miri fade-up d3">
    <div class="text"><?= h(mesaj_bun_venit()) ?></div>
    <div class="semnatura"><?= h(NUME_MIRE) ?> &amp; <?= h(NUME_MIREASA) ?></div>
  </div>
</div>

<section class="sectiune">
  <div class="container">
    <div class="sectiune-titlu fade-up">
      <div class="ornament"><span class="ln"></span><span class="dot"></span><span class="ln r"></span></div>
      <h2><?= h(text('tx_incarca_titlu')) ?></h2>
      <p><?= h(text('tx_incarca_desc')) ?></p>
    </div>

    <div class="upload-wrap fade-up">
      <div id="zona-upload">
        <div class="dropzone" id="dropzone" tabindex="0" role="button" aria-label="Alege poze sau filme">
          <div class="icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" width="56" height="56">
              <path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5"/>
              <path d="M4 14v3.5A2.5 2.5 0 0 0 6.5 20h11a2.5 2.5 0 0 0 2.5-2.5V14"/>
            </svg>
          </div>
          <div class="titlu"><?= h(text('tx_drop_titlu')) ?></div>
          <div class="desc"><?= h(text('tx_drop_desc')) ?></div>
          <div class="formate">JPG · PNG · HEIC · GIF · MP4 · MOV</div>
        </div>
        <input type="file" id="input-fisiere" accept="image/*,video/*" multiple hidden>

        <div class="campuri">
          <div class="camp">
            <label for="nume"><?= h(text('tx_nume_eticheta')) ?></label>
            <input type="text" id="nume" maxlength="120" placeholder="ex: Familia Popescu">
            <div class="ajutor-camp"><?= h(text('tx_nume_ajutor')) ?></div>
          </div>
        </div>
        <?php /* Câmpul de mesaj a fost scos anume: există Cartea de urări,
                 iar două locuri unde scrii un gând îl fac pe invitat să se
                 întrebe care e diferența. Rămâne: alege fișiere, scrie-ți
                 numele, apasă. */ ?>

        <div class="lista-fisiere" id="lista-fisiere"></div>

        <div style="margin-top:18px;text-align:center">
          <button class="btn btn-primar btn-full" id="btn-incarca" disabled><?= h(text('tx_buton_incarca')) ?></button>
        </div>
      </div>

      <div class="card succes-box" id="zona-succes" style="display:none">
        <div class="check" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="34" height="34"><path d="M20 6L9 17l-5-5"/></svg>
        </div>
        <h3><?= h(text('tx_succes_titlu')) ?></h3>
        <p id="succes-text">Pozele tale au fost adăugate în album.</p>
        <div style="margin-top:18px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
          <a class="btn btn-primar" href="galerie">Vezi galeria</a>
          <button class="btn btn-ghost" id="btn-din-nou">Mai încarcă</button>
        </div>
      </div>
    </div>
  </div>
</section>



<?php if ($pozeRecente): ?>
<section class="sectiune" style="padding:34px 0 6px">
  <div class="container">
    <div class="sectiune-titlu" style="margin-bottom:18px">
      <div class="ornament"><span class="ln"></span><span class="dot"></span><span class="ln r"></span></div>
      <h2 style="font-size:clamp(1.7rem,4vw,2.3rem)"><?= h(text('tx_recente_titlu')) ?></h2>
    </div>
    <div class="galerie">
      <?php foreach ($pozeRecente as $p): ?>
        <a class="poza vizibil" href="galerie" aria-label="Vezi galeria">
          <?php if ($p['tip'] === 'video' && !are_miniatura($p)): ?>
            <?php /* Fără cadrul trimis de telefon nu există miniatură; arătăm
                     chiar filmul, cerut doar cât să se vadă primul cadru. */ ?>
            <video src="<?= h(url_original($p)) ?>#t=0.1" preload="metadata" muted playsinline tabindex="-1"></video>
          <?php else: ?>
            <img loading="lazy" src="<?= h(url_previzualizare($p)) ?>" alt="">
          <?php endif; ?>
          <?php if ($p['tip'] === 'video'): ?><div class="play"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></div><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:20px"><a class="btn btn-ghost" href="galerie">Vezi toată galeria</a></div>
  </div>
</section>
<?php endif; ?>

<?php if ($urariRecente): ?>
<section class="sectiune" style="padding:18px 0 6px">
  <div class="container">
    <div class="sectiune-titlu" style="margin-bottom:18px">
      <div class="ornament"><span class="ln"></span><span class="dot"></span><span class="ln r"></span></div>
      <h2 style="font-size:clamp(1.7rem,4vw,2.3rem)"><?= h(text('tx_urari_titlu')) ?></h2>
    </div>
    <div class="urari-grid">
      <?php foreach ($urariRecente as $u): ?>
        <figure class="urare-card fade-up">
          <blockquote class="urare-text">„<?= h($u['mesaj']) ?>”</blockquote>
          <figcaption class="urare-semn"><?= h($u['nume']) ?><span class="urare-data"><?= date('d.m.Y', strtotime($u['data_creare'])) ?></span></figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:20px"><a class="btn btn-ghost" href="urari">Vezi toate urările</a></div>
  </div>
</section>
<?php endif; ?>

<?php subsol_pagina(); ?>