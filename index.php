<?php
require_once __DIR__ . '/partiale.php';
asigura_schema();

$pozeRecente  = [];
$urariRecente = [];
/* Banda care se rotește pe prima pagină. Momentele se aleg amestecat din
   TOT albumul, nu doar dintre cele noi: la miezul nopții se văd și pozele
   de la venirea invitaților, nu numai ultimele zece minute. La fiecare
   deschidere a paginii iese altă selecție.

   Amestecarea se face pe id-uri, nu pe rândurile întregi: baza de date
   sortează atunci o listă de numere, nu tot conținutul tabelei.

   Cerem mai multe decât arătăm și le punem întâi pe cele cu miniatură (o
   miniatură are ~55 KB, originalul unei poze de telefon peste un
   megaoctet). Filmele intră și ele, dar NUMAI ca miniatură — niciun
   element de film pe prima pagină, ca nimic să nu pornească singur. */
define('BANDA_MOMENTE', 14);
try {
    $ids = db()->query('SELECT id FROM poze WHERE aprobat = 1 ORDER BY RAND() LIMIT 40')
                ->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) {
        $sem = implode(',', array_fill(0, count($ids), '?'));
        $st  = db()->prepare("SELECT * FROM poze WHERE id IN ($sem)");
        $st->execute(array_map('intval', $ids));
        $candidati = $st->fetchAll();
        /* „IN" întoarce rândurile în ordinea lui, nu în a noastră — le
           amestecăm din nou aici, altfel banda ar ieși mereu crescător. */
        shuffle($candidati);

        $cuMiniatura = []; $faraMiniatura = [];
        foreach ($candidati as $c) {
            if (are_miniatura($c)) $cuMiniatura[] = $c; else $faraMiniatura[] = $c;
        }
        $pozeRecente = array_slice(array_merge($cuMiniatura, $faraMiniatura), 0, BANDA_MOMENTE);
    }
} catch (Throwable $e) {}
/* Urările curg la fel ca momentele: amestecate din toată cartea, ca să nu
   rămână aceleași șase pe toată seara. Aceeași socoteală ca la poze —
   amestecăm id-uri, nu rânduri întregi. */
define('BANDA_URARI', 12);
try {
    $idsU = db()->query('SELECT id FROM urari WHERE aprobat = 1 ORDER BY RAND() LIMIT ' . BANDA_URARI)
                 ->fetchAll(PDO::FETCH_COLUMN);
    if ($idsU) {
        $sem = implode(',', array_fill(0, count($idsU), '?'));
        $st  = db()->prepare("SELECT nume, mesaj, data_creare FROM urari WHERE id IN ($sem)");
        $st->execute(array_map('intval', $idsU));
        $urariRecente = $st->fetchAll();
        shuffle($urariRecente);
    }
} catch (Throwable $e) {}

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
          <?php /* Lista de formate a fost scoasă anume: invitatului nu-i
                   spune nimic „HEIC" sau „M4V", iar ce trimite telefonul lui
                   e primit oricum. Acceptarea din cod a rămas neschimbată. */ ?>
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
    <?php
      /* Banda merge la nesfârșit pentru că lista e scrisă de două ori, iar
         animația mută pista exact cu o jumătate din ea. Când ajunge acolo,
         a doua copie stă fix unde stătea prima — se reia fără nicio
         săritură.

         Dar la începutul serii albumul are două-trei poze, iar o tură de
         câteva sute de puncte nu acoperă un ecran de laptop: banda s-ar
         roti cu un gol în urma ei. Așa că repetăm lista până când o tură
         trece de un ecran lat, și abia apoi o dublăm pentru buclă. Cu
         albumul plin nu se repetă nimic. */
      $minPeTura = 9;                       // 9 × (210 + 14) ≈ 2000 puncte
      $set = $pozeRecente;
      if ($set) { while (count($set) < $minPeTura) $set = array_merge($set, $pozeRecente); }
      $originale = count($pozeRecente);
      $durata    = max(20, count($set) * 4);   // viteza rămâne aceeași
    ?>
    <div class="banda" aria-label="Cele mai noi momente din album">
      <div class="banda-pista" style="animation-duration:<?= (int)$durata ?>s">
        <?php for ($copie = 0; $copie < 2; $copie++): ?>
          <?php foreach ($set as $k => $p):
            /* Cititorului de ecran îi dăm o singură dată fiecare moment:
               copiile pentru buclă și repetările de la începutul serii
               sunt doar decor. */
            $decor = $copie > 0 || $k >= $originale;
          ?>
            <?php /* Legătura duce la momentul ANUME, nu doar la galerie:
                     apeși pe un film și se deschide chiar el, acolo unde
                     are voie să pornească. */ ?>
            <a class="banda-item" href="galerie#m<?= (int)$p['id'] ?>"
               <?= $decor ? 'aria-hidden="true" tabindex="-1"' : 'aria-label="' . ($p['tip'] === 'video' ? 'Vezi filmul în galerie' : 'Vezi fotografia în galerie') . '"' ?>>
              <?php if (are_miniatura($p)): ?>
                <img loading="lazy" decoding="async" src="<?= h(url_previzualizare($p)) ?>" alt="">
              <?php else: ?>
                <?php /* Fără miniatură nu punem originalul aici: pe o poză
                         de telefon ar fi peste un megaoctet, iar la un film
                         zeci. Rămâne un loc gol, discret. */ ?>
                <span class="banda-gol" aria-hidden="true"></span>
              <?php endif; ?>
              <?php if ($p['tip'] === 'video'): ?>
                <span class="banda-play" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M8 5v14l11-7z"/></svg>
                </span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        <?php endfor; ?>
      </div>
    </div>
    <div style="text-align:center;margin-top:22px"><a class="btn btn-ghost" href="galerie">Vezi toată galeria</a></div>
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
    <?php
      /* Aceeași socoteală ca la bandă de poze: repetăm lista până când o
         tură trece de un ecran lat, apoi o dublăm pentru buclă. Cu două
         urări scrise, banda s-ar roti altfel cu un gol în urma ei.
         Cartonașele sunt mai late decât plăcile cu poze, deci ajung mai
         puține pe o tură. */
      $minUrariPeTura = 7;                      // 7 × (300 + 16) ≈ 2200 puncte
      $setU = $urariRecente;
      /* Paza nu e de prisos: fără ea, o listă goală ar repeta la nesfârșit
         un nimic și ar ține pagina agățată. Astăzi secțiunea nu se
         afișează când nu există urări, dar bucla nu trebuie să depindă de
         un „dacă" aflat la cincisprezece rânduri distanță. */
      if ($setU) { while (count($setU) < $minUrariPeTura) $setU = array_merge($setU, $urariRecente); }
      $originaleU = count($urariRecente);
      /* Urările se citesc, nu se privesc: le lăsăm să treacă mai încet. */
      $durataU = max(30, count($setU) * 7);
    ?>
    <div class="banda banda-urari" aria-label="Urări de la invitați">
      <div class="banda-pista" style="animation-duration:<?= (int)$durataU ?>s">
        <?php for ($copie = 0; $copie < 2; $copie++): ?>
          <?php foreach ($setU as $k => $u):
            $decor = $copie > 0 || $k >= $originaleU;
          ?>
            <a class="urare-placa" href="urari"
               <?= $decor ? 'aria-hidden="true" tabindex="-1"' : 'aria-label="Vezi toate urările"' ?>>
              <span class="urare-corp"><span class="urare-text">„<?= h($u['mesaj']) ?>”</span></span>
              <span class="urare-semn">
                <span class="nume"><?= h($u['nume']) ?></span>
                <span class="urare-data"><?= date('d.m.Y', strtotime($u['data_creare'])) ?></span>
              </span>
            </a>
          <?php endforeach; ?>
        <?php endfor; ?>
      </div>
    </div>
    <div style="text-align:center;margin-top:22px"><a class="btn btn-ghost" href="urari">Vezi toate urările</a></div>
  </div>
</section>
<?php endif; ?>

<?php subsol_pagina(); ?>