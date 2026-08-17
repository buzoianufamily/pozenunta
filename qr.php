<?php
/* ============================================================
   CARTONAȘUL CU COD QR — pentru mesele invitaților
   ------------------------------------------------------------
   Se poate schimba textul, scrisul și ce se arată, iar
   cartonașul se vede pe loc, fără salvare. Salvarea îl face
   permanent (setările stau în baza de date, ca restul).

   Codul QR conține DOAR adresa site-ului. Nu se schimbă oricâte
   modificări am face aici sau în aplicație — deci poate fi
   printat liniștit.
   ============================================================ */
require_once __DIR__ . '/functions.php';
cere_admin();
asigura_schema();

/* Valorile implicite, dacă nu s-a schimbat nimic încă. */
$IMPLICITE = [
    'qr_indemn'        => 'Scanează-mă',
    'qr_indiciu'       => 'împărtășește momentele cu noi',
    'qr_nume'          => NUME_MIRE . ' & ' . NUME_MIREASA,
    'qr_scris'         => 'ScrisParisienne',
    'qr_sageata'       => '1',
    'qr_sageata_sus'   => '4',        // cât de jos stă săgeata (px): mai mare = mai aproape de cod
    'qr_data'          => '1',
    'qr_adresa'        => '1',
    'qr_inima'         => '0',        // inimioara decorativă, oprită implicit
    'qr_inima_culoare' => '#BF9B4F',  // auriul mărcii
];

/* Culorile permise pentru inimioară — o listă scurtă, ca să nu ajungă
   orice valoare în pagină. */
$CULORI_INIMA = [
    '#BF9B4F' => 'auriu',
    '#0D3328' => 'verde',
    '#C0432F' => 'roșu',
    '#D98E9E' => 'roz',
    '#8A8071' => 'gri cald',
];

$notif = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? '')) {
        $notif = ['err', 'Sesiune expirată. Reîncarcă pagina.'];
    } elseif (($_POST['actiune'] ?? '') === 'implicit') {
        foreach (array_keys($IMPLICITE) as $k) salveaza_setare($k, $IMPLICITE[$k]);
        $notif = ['ok', 'S-a revenit la varianta de pornire.'];
    } else {
        $scrisuriOk = ['ScrisCaveat', 'ScrisParisienne', 'ScrisVibes'];
        salveaza_setare('qr_indemn',  mb_substr(trim((string)($_POST['qr_indemn']  ?? '')), 0, 40));
        salveaza_setare('qr_indiciu', mb_substr(trim((string)($_POST['qr_indiciu'] ?? '')), 0, 60));
        salveaza_setare('qr_nume',    mb_substr(trim((string)($_POST['qr_nume']    ?? '')), 0, 60));
        $scris = (string)($_POST['qr_scris'] ?? '');
        salveaza_setare('qr_scris', in_array($scris, $scrisuriOk, true) ? $scris : $IMPLICITE['qr_scris']);
        foreach (['qr_sageata', 'qr_data', 'qr_adresa', 'qr_inima'] as $k) {
            salveaza_setare($k, empty($_POST[$k]) ? '0' : '1');
        }
        /* Poziția săgeții: o ținem între 0 și 60 px, ca să nu iasă din cartonaș. */
        $sus = (int)($_POST['qr_sageata_sus'] ?? 4);
        salveaza_setare('qr_sageata_sus', (string)max(0, min(60, $sus)));
        /* Culoarea inimii: doar din lista permisă. */
        $culoare = (string)($_POST['qr_inima_culoare'] ?? '');
        salveaza_setare('qr_inima_culoare', isset($CULORI_INIMA[$culoare]) ? $culoare : $IMPLICITE['qr_inima_culoare']);
        $notif = ['ok', 'Salvat. Cartonașul arată așa și după ce închizi pagina.'];
    }
}

function qr_setare(string $cheie) {
    global $IMPLICITE;
    $v = setare($cheie, null);
    return ($v === null || $v === '') ? ($IMPLICITE[$cheie] ?? '') : $v;
}

$url = SITE_URL ?: ((($_SERVER['HTTPS'] ?? '') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? ''));

/* Mărimile diferă de la un scris la altul: literele au proporții
   diferite, iar un „Scanează-mă" prea lat s-ar rupe pe două rânduri. */
$MARIMI = [
    'ScrisCaveat'     => ['indemn' => '2.65rem', 'nume' => '2.25rem', 'spatiu' => '0'],
    'ScrisParisienne' => ['indemn' => '2.25rem', 'nume' => '1.95rem', 'spatiu' => '.01em'],
    'ScrisVibes'      => ['indemn' => '2.45rem', 'nume' => '2.05rem', 'spatiu' => '.04em'],
];
$scrisActiv = qr_setare('qr_scris');
$m = $MARIMI[$scrisActiv] ?? $MARIMI['ScrisParisienne'];
?><!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="icon" href="assets/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
<title>Cod QR · <?= h(NUME_MIRE) ?> &amp; <?= h(NUME_MIREASA) ?></title>
<link rel="stylesheet" href="assets/fonturi.css">
<link rel="stylesheet" href="assets/fonturi-qr.css">
<link rel="stylesheet" href="assets/style.css">
<style>
  body{background:#F2EEE7}
  .qr-ecran{max-width:1000px;margin:0 auto;padding:30px 18px 60px;
    display:grid;grid-template-columns:minmax(280px,340px) 1fr;gap:34px;align-items:start}
  @media (max-width:820px){ .qr-ecran{grid-template-columns:1fr} }

  /* ---------- cartonașul ---------- */
  .cartonas{position:relative;background:var(--crem,#FBF7EF);border:1px solid #E4DFD6;border-radius:16px;
    padding:26px 22px 22px;text-align:center;box-shadow:0 18px 40px -22px rgba(0,0,0,.45);
    aspect-ratio:3.4/5.3;display:flex;flex-direction:column;align-items:center;justify-content:space-between}
  .cartonas::before{content:"";position:absolute;inset:9px;border:1px solid rgba(191,155,79,.34);
    border-radius:10px;pointer-events:none}
  .c-sus{position:relative;width:100%;padding-right:50px}
  .c-indemn{font-family:var(--scris),cursive;font-size:var(--s-indemn);line-height:1.05;
    color:#0D3328;white-space:nowrap}
  .c-sageata{position:absolute;top:var(--sageata-sus,4px);right:-6px;width:60px;height:66px;color:#BF9B4F;transition:top .15s}
  .c-qr-zona{position:relative;padding:12px;background:#fff;border-radius:8px}
  .c-colt{position:absolute;width:18px;height:18px;border:2.8px solid #0D3328}
  .c-colt.ss{top:-6px;left:-6px;border-right:0;border-bottom:0;border-radius:6px 0 0 0}
  .c-colt.sd{top:-6px;right:-6px;border-left:0;border-bottom:0;border-radius:0 6px 0 0}
  .c-colt.js{bottom:-6px;left:-6px;border-right:0;border-top:0;border-radius:0 0 0 6px}
  .c-colt.jd{bottom:-6px;right:-6px;border-left:0;border-top:0;border-radius:0 0 6px 0}
  #qrcode img,#qrcode canvas{display:block}
  .c-jos{width:100%}
  .c-indiciu{font-size:.66rem;letter-spacing:.22em;text-transform:uppercase;color:#8A8071;margin-bottom:5px}
  .c-nume{font-family:var(--scris),cursive;font-size:var(--s-nume);line-height:1.16;
    letter-spacing:var(--ls-nume);color:#0D3328;white-space:nowrap}
  .c-nume .amp{color:#BF9B4F;margin:0 .12em}
  .c-inima{margin-top:8px;color:var(--inima,#BF9B4F);line-height:0}
  .c-inima svg{display:inline-block}
  .c-data{font-size:.62rem;letter-spacing:.2em;text-transform:uppercase;color:#8A8071;margin-top:6px}
  .c-adresa{font-size:.71rem;color:#BF9B4F;margin-top:3px}
  [hidden]{display:none !important}

  /* ---------- panoul de setări ---------- */
  .reglaje .camp{margin-bottom:16px}
  .reglaje label{display:block;font-size:.8rem;letter-spacing:.1em;text-transform:uppercase;
    color:var(--muted);margin-bottom:6px}
  .reglaje input[type=text]{width:100%;padding:11px 13px;border:1px solid var(--line);
    border-radius:10px;font:inherit;background:#fff}
  .scrisuri{display:flex;gap:10px;flex-wrap:wrap}
  .scris-op{flex:1;min-width:130px;cursor:pointer}
  .scris-op input{position:absolute;opacity:0;pointer-events:none}
  .scris-op span{display:block;text-align:center;padding:12px 8px;border:1px solid var(--line);
    border-radius:12px;background:#fff;font-size:1.5rem;line-height:1.1;color:#0D3328}
  .scris-op input:checked + span{border-color:#0D3328;box-shadow:0 0 0 2px rgba(13,51,40,.15)}
  /* Eticheta rămâne cu scrisul obișnuit — altfel ar moșteni scrisul de
     mână din previzualizare și s-ar citi greu. */
  .scris-op em{display:block;font-family:var(--sans);font-size:.7rem;letter-spacing:.12em;
    text-transform:uppercase;color:var(--muted);font-style:normal;margin-top:5px}
  .comutatoare{display:flex;flex-direction:column;gap:9px}
  .comutator{display:flex;align-items:center;gap:10px;font-size:.92rem;cursor:pointer}
  .culori{display:flex;gap:12px;flex-wrap:wrap}
  .culoare-op{cursor:pointer;line-height:0}
  .culoare-op input{position:absolute;opacity:0;pointer-events:none}
  .culoare-op span{display:block;width:34px;height:34px;border-radius:50%;
    border:2px solid transparent;box-shadow:0 0 0 1px rgba(0,0,0,.12) inset}
  .culoare-op input:checked + span{border-color:#0D3328;box-shadow:0 0 0 2px #fff inset,0 0 0 3px #0D3328}
  /* Stilul general al formularelor întinde orice input pe toată lățimea;
     la bife asta le-ar împinge eticheta pe rândul următor. */
  .reglaje input[type=checkbox],.reglaje input[type=radio]{
    width:auto;flex:0 0 auto;margin:0;padding:0;accent-color:#0D3328}
  .mic{font-size:.84rem;color:var(--muted)}

  @media print{
    body{background:#fff}
    .nu-printa{display:none !important}
    .qr-ecran{display:block;padding:0;max-width:none}
    .cartonas{width:88mm;margin:0 auto;box-shadow:none;border-color:#ddd}
  }
</style>
</head>
<body>

<div class="qr-ecran">

  <!-- ============ CARTONAȘUL ============ -->
  <div class="cartonas" id="cartonas"
       style="--scris:'<?= h($scrisActiv) ?>';--s-indemn:<?= h($m['indemn']) ?>;--s-nume:<?= h($m['nume']) ?>;--ls-nume:<?= h($m['spatiu']) ?>;--sageata-sus:<?= (int)qr_setare('qr_sageata_sus') ?>px;--inima:<?= h(qr_setare('qr_inima_culoare')) ?>">
    <div class="c-sus">
      <div class="c-indemn" id="p-indemn"><?= h(qr_setare('qr_indemn')) ?></div>
      <svg class="c-sageata" id="p-sageata" viewBox="0 0 120 130" aria-hidden="true"
           <?= qr_setare('qr_sageata') === '1' ? '' : 'hidden' ?>>
        <path d="M14 14 C 70 -4, 118 22, 104 62 C 95 90, 68 108, 40 112"
              fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
        <path d="M58 100 L 37 113 L 53 126" fill="none" stroke="currentColor"
              stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>

    <div class="c-qr-zona">
      <span class="c-colt ss"></span><span class="c-colt sd"></span>
      <span class="c-colt js"></span><span class="c-colt jd"></span>
      <div id="qrcode"></div>
    </div>

    <div class="c-jos">
      <div class="c-indiciu" id="p-indiciu"><?= h(qr_setare('qr_indiciu')) ?></div>
      <div class="c-nume" id="p-nume"></div>
      <div class="c-inima" id="p-inima" <?= qr_setare('qr_inima') === '1' ? '' : 'hidden' ?>>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <path d="M12 21s-7.5-4.6-10-9.3C.4 8.3 2 5 5.3 5c2 0 3.4 1.2 4.2 2.4C10.3 6.2 11.7 5 13.7 5 17 5 18.6 8.3 17 11.7 14.5 16.4 12 21 12 21z"/>
        </svg>
      </div>
      <div class="c-data" id="p-data" <?= qr_setare('qr_data') === '1' ? '' : 'hidden' ?>><?= h(DATA_NUNTII) ?></div>
      <div class="c-adresa" id="p-adresa" <?= qr_setare('qr_adresa') === '1' ? '' : 'hidden' ?>><?= h(preg_replace('#^https?://#', '', rtrim($url, '/'))) ?></div>
    </div>
  </div>

  <!-- ============ REGLAJELE ============ -->
  <div class="nu-printa">
    <div class="panou">
      <h2>Cartonașul cu cod QR</h2>
      <p class="ajutor">Schimbă ce vrei — cartonașul din stânga se modifică pe loc.
        Apasă <strong>Salvează</strong> ca să rămână așa și data viitoare.</p>

      <?php if ($notif): ?>
        <div class="alerta <?= $notif[0] === 'ok' ? 'ok' : '' ?>"><?= h($notif[1]) ?></div>
      <?php endif; ?>

      <form method="post" class="reglaje" id="form-qr">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

        <div class="camp">
          <label for="qr_indemn">Textul de sus</label>
          <input type="text" id="qr_indemn" name="qr_indemn" maxlength="40"
                 value="<?= h(qr_setare('qr_indemn')) ?>">
          <div class="mic">Scurt, ca să încapă pe un rând.</div>
        </div>

        <div class="camp">
          <label for="qr_indiciu">Rândul mic de deasupra numelor</label>
          <input type="text" id="qr_indiciu" name="qr_indiciu" maxlength="60"
                 value="<?= h(qr_setare('qr_indiciu')) ?>">
        </div>

        <div class="camp">
          <label for="qr_nume">Numele</label>
          <input type="text" id="qr_nume" name="qr_nume" maxlength="60"
                 value="<?= h(qr_setare('qr_nume')) ?>">
          <div class="mic">Semnul &amp; se colorează singur cu auriu.</div>
        </div>

        <div class="camp">
          <label>Scrisul</label>
          <div class="scrisuri">
            <?php foreach ([
              'ScrisCaveat'     => ['De mână', 'Caveat'],
              'ScrisParisienne' => ['Romantic', 'Parisienne'],
              'ScrisVibes'      => ['Caligrafic', 'Great Vibes'],
            ] as $val => [$et, $numeFont]): ?>
              <label class="scris-op">
                <input type="radio" name="qr_scris" value="<?= h($val) ?>"
                       <?= $scrisActiv === $val ? 'checked' : '' ?>>
                <span style="font-family:'<?= h($val) ?>',cursive">Aa
                  <em><?= h($et) ?></em>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="camp">
          <label>Ce se arată</label>
          <div class="comutatoare">
            <label class="comutator"><input type="checkbox" name="qr_sageata" value="1" id="c-sageata"
              <?= qr_setare('qr_sageata') === '1' ? 'checked' : '' ?>> Săgeata către cod</label>
            <label class="comutator"><input type="checkbox" name="qr_data" value="1"
              <?= qr_setare('qr_data') === '1' ? 'checked' : '' ?>> Data nunții</label>
            <label class="comutator"><input type="checkbox" name="qr_adresa" value="1"
              <?= qr_setare('qr_adresa') === '1' ? 'checked' : '' ?>> Adresa scrisă sub cod</label>
            <label class="comutator"><input type="checkbox" name="qr_inima" value="1" id="c-inima"
              <?= qr_setare('qr_inima') === '1' ? 'checked' : '' ?>> Inimioară sub nume</label>
          </div>
          <div class="mic" style="margin-top:8px">Adresa scrisă ajută invitații care nu reușesc să scaneze.</div>
        </div>

        <div class="camp" id="camp-sageata" <?= qr_setare('qr_sageata') === '1' ? '' : 'hidden' ?>>
          <label for="qr_sageata_sus">Cât de aproape e săgeata de cod</label>
          <input type="range" id="qr_sageata_sus" name="qr_sageata_sus" min="0" max="60" step="2"
                 value="<?= (int)qr_setare('qr_sageata_sus') ?>" style="width:100%;accent-color:#0D3328">
          <div class="mic">Trage spre dreapta ca s-o apropii de cod.</div>
        </div>

        <div class="camp" id="camp-inima" <?= qr_setare('qr_inima') === '1' ? '' : 'hidden' ?>>
          <label>Culoarea inimioarei</label>
          <div class="culori">
            <?php foreach ($CULORI_INIMA as $hex => $nume): ?>
              <label class="culoare-op" title="<?= h($nume) ?>">
                <input type="radio" name="qr_inima_culoare" value="<?= h($hex) ?>"
                       <?= qr_setare('qr_inima_culoare') === $hex ? 'checked' : '' ?>>
                <span style="background:<?= h($hex) ?>"></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px">
          <button class="btn btn-primar" type="submit">Salvează</button>
          <button class="btn btn-ghost" type="button" onclick="window.print()">Printează</button>
        </div>
      </form>

      <form method="post" style="margin-top:12px"
            onsubmit="return confirm('Revii la textele de pornire?')">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="actiune" value="implicit">
        <button class="btn btn-ghost btn-mic" type="submit">Revino la varianta de pornire</button>
      </form>
    </div>

    <div class="panou">
      <h2>De știut</h2>
      <p class="ajutor" style="margin-bottom:0">
        <strong>Codul QR nu se schimbă niciodată aici.</strong> El conține doar adresa
        <code><?= h($url) ?></code>. Poți modifica textele oricând, chiar și după ce ai printat —
        codul rămâne valabil.<br><br>
        Singurul lucru care l-ar schimba e <code>SITE_URL</code> din <code>config.php</code>.
        Atâta vreme cât adresa rămâne aceeași, cartonașele printate rămân bune.<br><br>
        La printare se vede doar cartonașul, fără reglaje, la lățime de 88 mm.
      </p>
    </div>
  </div>

</div>

<script src="assets/vendor/qrcode.min.js"></script>
<script>
(function () {
  var ADRESA = <?= json_encode($url) ?>;
  new QRCode(document.getElementById('qrcode'), {
    text: ADRESA, width: 150, height: 150,
    colorDark: '#0D3328', colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.M
  });

  /* Mărimile potrivite fiecărui scris, aceleași ca pe server. */
  var MARIMI = {
    ScrisCaveat:     ['2.65rem', '2.25rem', '0'],
    ScrisParisienne: ['2.25rem', '1.95rem', '.01em'],
    ScrisVibes:      ['2.45rem', '2.05rem', '.04em']
  };

  var cartonas = document.getElementById('cartonas');
  function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

  function leaga(idCamp, idPreview, gol) {
    var c = document.getElementById(idCamp), p = document.getElementById(idPreview);
    if (!c || !p) return;
    c.addEventListener('input', function () { p.textContent = c.value.trim() || gol; });
  }
  leaga('qr_indemn',  'p-indemn',  'Scanează-mă');
  leaga('qr_indiciu', 'p-indiciu', '');

  /* Numele: colorăm „&" cu auriu, ca pe restul site-ului. */
  var campNume = document.getElementById('qr_nume'), prevNume = document.getElementById('p-nume');
  function scrieNume() {
    var t = (campNume.value || '').trim();
    prevNume.innerHTML = esc(t).replace(/\s*&amp;\s*/g, ' <span class="amp">&amp;</span> ');
  }
  campNume.addEventListener('input', scrieNume);
  scrieNume();

  document.querySelectorAll('input[name="qr_scris"]').forEach(function (r) {
    r.addEventListener('change', function () {
      var v = MARIMI[r.value] || MARIMI.ScrisParisienne;
      cartonas.style.setProperty('--scris', "'" + r.value + "'");
      cartonas.style.setProperty('--s-indemn', v[0]);
      cartonas.style.setProperty('--s-nume', v[1]);
      cartonas.style.setProperty('--ls-nume', v[2]);
    });
  });

  [['qr_sageata','p-sageata'], ['qr_data','p-data'], ['qr_adresa','p-adresa'], ['qr_inima','p-inima']].forEach(function (p) {
    var c = document.querySelector('input[name="' + p[0] + '"]'), el = document.getElementById(p[1]);
    if (!c || !el) return;
    /* Punem chiar atributul, nu proprietatea: săgeata și inima sunt SVG,
       iar acolo proprietatea „hidden" nu e sigură pe toate browserele —
       atributul, de care ține regula din CSS, funcționează peste tot. */
    c.addEventListener('change', function () {
      if (c.checked) el.removeAttribute('hidden'); else el.setAttribute('hidden', '');
    });
  });

  /* Săgeata: cursorul o apropie de cod, iar reglajul lui apare doar când
     săgeata e pornită. */
  var slider = document.getElementById('qr_sageata_sus');
  if (slider) {
    slider.addEventListener('input', function () {
      cartonas.style.setProperty('--sageata-sus', slider.value + 'px');
    });
  }
  function aratREglaj(idBifa, idCamp) {
    var b = document.getElementById(idBifa), camp = document.getElementById(idCamp);
    if (!b || !camp) return;
    b.addEventListener('change', function () {
      if (b.checked) camp.removeAttribute('hidden'); else camp.setAttribute('hidden', '');
    });
  }
  aratREglaj('c-sageata', 'camp-sageata');
  aratREglaj('c-inima',   'camp-inima');

  /* Culoarea inimii, pe loc. */
  document.querySelectorAll('input[name="qr_inima_culoare"]').forEach(function (r) {
    r.addEventListener('change', function () {
      cartonas.style.setProperty('--inima', r.value);
    });
  });
})();
</script>
</body>
</html>
