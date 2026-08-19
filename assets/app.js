/* ============================================================
   BUZONNECT · Album nuntă — JavaScript (v2)
   - încărcare în paralel + reîncercare automată
   - reluare după închiderea telefonului (IndexedDB)
   - aprecieri (inimioare), sortare, galerie, lightbox, bandă
   ============================================================ */
(function () {
  'use strict';

  /* Semnul că fișierul acesta a ajuns și a pornit. Pagina îl caută după
     câteva secunde: dacă lipsește, înseamnă că nu s-a încărcat (o pană
     scurtă de rețea exact atunci) — iar atunci zona de încărcare arată
     bine, dar nu face nimic. Fără semnul ăsta, invitatul n-ar avea de
     unde ști de ce nu merge. */
  window.NUNTA_PORNIT = true;

  /* ---------- utilitare ---------- */
  var toastEl = document.getElementById('toast'), toastTimer;
  function toast(msg) {
    if (!toastEl) return;
    toastEl.textContent = msg; toastEl.classList.add('aratat');
    clearTimeout(toastTimer); toastTimer = setTimeout(function () { toastEl.classList.remove('aratat'); }, 3000);
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]; }); }
  function pauza(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }
  function esteVideo(f) { return /^video\//.test(f.type) || /\.(mp4|mov|webm|m4v|3gp|ogg)$/i.test(f.name || ''); }
  function esteImagine(f) { return /^image\//.test(f.type) || /\.(jpg|jpeg|png|gif|webp|heic|heif|bmp)$/i.test(f.name || ''); }

  /* ---------- aprecieri (partajat galerie + lightbox) ---------- */
  var CHEIE_LIKE = 'bz_likes';
  function likeSet() { try { return new Set(JSON.parse(localStorage.getItem(CHEIE_LIKE) || '[]')); } catch (e) { return new Set(); } }
  /* Atenție: Array.prototype.slice nu funcționează pe un Set — nu are
     lungime și nici indici, deci întorcea mereu un tablou gol. Din cauza
     asta nu se salva nimic, iar fiecare apăsare pe inimă părea o
     apreciere nouă: nu se mai putea retrage. */
  function salveazaLike(set) { try { localStorage.setItem(CHEIE_LIKE, JSON.stringify(Array.from(set))); } catch (e) {} }
  function esteApreciat(id) { return likeSet().has(id); }

  /* Ce se arată în galerie pentru un fișier.
     Filmele au de obicei o miniatură făcută din cadrul trimis de telefon.
     Când telefonul nu a reușit să-l scoată, miniatura lipsește — și
     atunci nu are rost să încercăm filmul într-o etichetă de imagine,
     pentru că nu se afișează nimic. Punem chiar filmul, cerut doar cât
     să se vadă primul cadru: „#t=0.1" îi spune browserului de unde. */
  function previzualizare(p, textAlternativ) {
    if (p.tip === 'video' && p.miniatura === false) {
      return '<video class="mini-video" src="' + esc(p.original) + '#t=0.1"'
           + ' preload="metadata" muted playsinline tabindex="-1"></video>';
    }
    /* „onerror": o miniatură care nu ajunge — sau care lipsește de pe disc —
       lăsa placa turtită la douăzeci de puncte, iar galeria părea goală.
       Marcăm placa și CSS-ul îi dă o formă normală, cu un semn discret. */
    return '<img loading="lazy" src="' + esc(p.preview) + '" alt="' + textAlternativ + '"'
         + ' onerror="this.closest(\'.poza\').classList.add(\'fara-imagine\');this.remove()">';
  }

  /* Serverul e sursa de adevăr: ține aprecierile pe invitat, nu pe
     telefon. Sincronizăm memoria locală cu ce spune el, ca inimile să
     arate corect și de pe alt dispozitiv. */
  function sincronizeazaLike(poze) {
    var set = likeSet(), schimbat = false;
    poze.forEach(function (p) {
      if (typeof p.apreciat !== 'boolean') return;
      if (p.apreciat && !set.has(p.id)) { set.add(p.id); schimbat = true; }
      else if (!p.apreciat && set.has(p.id)) { set.delete(p.id); schimbat = true; }
    });
    if (schimbat) salveazaLike(set);
  }

  function trimiteLike(id, val) {
    var fd = new FormData(); fd.append('id', id); fd.append('val', val);
    return fetch('like.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); }).catch(function () { return { ok: false }; });
  }

  /* ============================================================
     INDEXEDDB — coada de încărcare (pentru reluare după închidere)
     ============================================================ */
  var IDB_NUME = 'buzonnect_upload', IDB_STORE = 'coada', idbProm = null;
  function idb() {
    if (idbProm) return idbProm;
    idbProm = new Promise(function (res, rej) {
      if (!('indexedDB' in window)) { rej('no-idb'); return; }
      var r = indexedDB.open(IDB_NUME, 1);
      r.onupgradeneeded = function () { if (!r.result.objectStoreNames.contains(IDB_STORE)) r.result.createObjectStore(IDB_STORE, { keyPath: 'id' }); };
      r.onsuccess = function () { res(r.result); };
      r.onerror = function () { rej(r.error); };
    });
    return idbProm;
  }
  function idbPut(item) { return idb().then(function (db) { return new Promise(function (res, rej) { var tx = db.transaction(IDB_STORE, 'readwrite'); tx.objectStore(IDB_STORE).put(item); tx.oncomplete = function () { res(true); }; tx.onerror = function () { rej(tx.error); }; }); }); }
  function idbAll() { return idb().then(function (db) { return new Promise(function (res, rej) { var tx = db.transaction(IDB_STORE, 'readonly'); var rq = tx.objectStore(IDB_STORE).getAll(); rq.onsuccess = function () { res(rq.result || []); }; rq.onerror = function () { rej(rq.error); }; }); }); }
  function idbDel(id) { return idb().then(function (db) { return new Promise(function (res) { var tx = db.transaction(IDB_STORE, 'readwrite'); tx.objectStore(IDB_STORE).delete(id); tx.oncomplete = function () { res(true); }; tx.onerror = function () { res(false); }; }); }); }
  function idbClear() { return idb().then(function (db) { return new Promise(function (res) { var tx = db.transaction(IDB_STORE, 'readwrite'); tx.objectStore(IDB_STORE).clear(); tx.oncomplete = function () { res(true); }; tx.onerror = function () { res(false); }; }); }).catch(function () {}); }

  /* ---------- procesare imagine (HEIC -> JPG + micșorare) ---------- */
  var MAX_DIM = 2560, CALITATE = 0.85, heicProm = null;
  function incarcaHeic() {
    if (heicProm) return heicProm;
    /* Găzduit local: la nuntă, WiFi-ul sălii poate fi lent sau filtrat,
       iar fără această bibliotecă pozele de pe iPhone nu s-ar încărca. */
    heicProm = new Promise(function (res, rej) { var s = document.createElement('script'); s.src = 'assets/vendor/heic2any.min.js'; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
    return heicProm;
  }
  function proceseazaImagine(file) {
    return (async function () {
      var sursa = file;
      if (/heic|heif/i.test(file.type) || /\.(heic|heif)$/i.test(file.name)) {
        try { await incarcaHeic(); var conv = await window.heic2any({ blob: file, toType: 'image/jpeg', quality: 0.9 }); sursa = Array.isArray(conv) ? conv[0] : conv; }
        catch (e) { return file; }
      }
      try {
        var bmp;
        try { bmp = await createImageBitmap(sursa, { imageOrientation: 'from-image' }); } catch (_) { bmp = await createImageBitmap(sursa); }
        var scale = Math.min(1, MAX_DIM / Math.max(bmp.width, bmp.height));
        var nw = Math.round(bmp.width * scale), nh = Math.round(bmp.height * scale);
        var c = document.createElement('canvas'); c.width = nw; c.height = nh; c.getContext('2d').drawImage(bmp, 0, 0, nw, nh);
        if (bmp.close) bmp.close();
        var blob = await new Promise(function (r) { c.toBlob(r, 'image/jpeg', CALITATE); });
        if (!blob) return sursa;
        var baza = (file.name || 'poza').replace(/\.[^.]+$/, '');
        return new File([blob], baza + '.jpg', { type: 'image/jpeg' });
      } catch (e) { return sursa; }
    })();
  }

  /* ---------- poster pentru filme (primul cadru, generat pe telefon) ---------- */
  function posterVideo(file) {
    return new Promise(function (resolve) {
      try {
        var url = URL.createObjectURL(file);
        var v = document.createElement('video');
        v.muted = true; v.playsInline = true; v.preload = 'metadata'; v.src = url;
        var gata = false;
        function termina(blob) { if (gata) return; gata = true; try { URL.revokeObjectURL(url); } catch (e) {} resolve(blob); }
        v.addEventListener('loadeddata', function () {
          /* Pe iPhone, un film care n-a rulat niciodată se desenează pe
             pânză ca un dreptunghi negru: decodorul nu are încă un cadru.
             O pornire scurtă, fără sunet, îl face să pregătească unul.
             Dacă telefonul refuză pornirea, mergem mai departe oricum —
             pe alte telefoane cadrul e deja acolo. */
          var porneste = null;
          try { porneste = v.play(); } catch (e) {}
          Promise.resolve(porneste).catch(function () {}).then(function () {
            try { v.pause(); } catch (e) {}
            try { var d = (v.duration && isFinite(v.duration)) ? v.duration : 2; v.currentTime = Math.min(1, d / 2); }
            catch (e) { termina(null); }
          });
        });

        /* Un cadru complet negru nu e o miniatură, e o pată. Mai rău,
           serverul l-ar lua drept miniatură bună și galeria ar arăta un
           pătrat negru în loc să se descurce altfel. Pipăim câteva puncte
           și, dacă toate sunt la fel de întunecate, spunem că n-am reușit. */
        function cadruGol(ctx, w, h) {
          try {
            var pasX = Math.max(1, Math.floor(w / 8)), pasY = Math.max(1, Math.floor(h / 8)), maxim = 0;
            for (var x = 0; x < w; x += pasX) {
              for (var y = 0; y < h; y += pasY) {
                var p = ctx.getImageData(x, y, 1, 1).data;
                var lum = 0.299 * p[0] + 0.587 * p[1] + 0.114 * p[2];
                if (lum > maxim) maxim = lum;
              }
            }
            return maxim < 12;         // tot ce am pipăit e practic negru
          } catch (e) { return false; }  // pânză „murdărită": nu putem ști, o păstrăm
        }

        v.addEventListener('seeked', function () {
          try {
            var w = v.videoWidth || 600, h = v.videoHeight || 400;
            var scale = Math.min(1, 700 / Math.max(w, h));
            var c = document.createElement('canvas');
            c.width = Math.round(w * scale) || 600; c.height = Math.round(h * scale) || 400;
            var ctx = c.getContext('2d');
            ctx.drawImage(v, 0, 0, c.width, c.height);
            if (cadruGol(ctx, c.width, c.height)) { termina(null); return; }
            c.toBlob(function (b) { termina(b); }, 'image/jpeg', 0.8);
          } catch (e) { termina(null); }
        });
        v.addEventListener('error', function () { termina(null); });
        setTimeout(function () { termina(null); }, 8000);
      } catch (e) { resolve(null); }
    });
  }

  /* ============================================================
     1) PAGINA DE ÎNCĂRCARE
     ============================================================ */
  var dropzone = document.getElementById('dropzone');
  if (dropzone) initUpload();

  function initUpload() {
    var input = document.getElementById('input-fisiere');
    var lista = document.getElementById('lista-fisiere');
    var btn = document.getElementById('btn-incarca');
    var zona = document.getElementById('zona-upload');
    var succes = document.getElementById('zona-succes');
    var succesTxt = document.getElementById('succes-text');
    var btnDinNou = document.getElementById('btn-din-nou');

    var coada = [];
    var poolRuleaza = false;
    /* Câte fișiere urcă odată. Fiecare ține ocupat un proces pe server,
       iar la o nuntă urcă zeci de telefoane în același timp — cu 3 de
       fiecare, serverul se îneacă și se încetinește tot site-ul. */
    var CONC = 2, MAX_TRIES = 4, BACKOFF = [800, 2000, 4000];

    /* Fișierele mai mari de atât se trimit pe bucăți, ca să poată fi
       reluate din punctul rămas. Pozele (micșorate pe telefon, ~1-2 MB)
       merg dintr-o bucată — e mai rapid și e drumul deja verificat. */
    var LIMITE = window.NUNTA || {};
    var PRAG_BUCATI = 8 * 1024 * 1024;
    /* Mărimea bucății vine de la server, care știe cât acceptă într-o
       cerere. Bucăți mai mari = mai puține cereri: un film de 700 MB
       cerea 175 de cereri cu bucăți de 4 MB, și doar 88 cu 8 MB. */
    var BUCATA = LIMITE.bucata || 4 * 1024 * 1024;
    /* Reluarea e ieftină (continuă din punctul rămas), deci are rost să
       insistăm mai mult înainte să renunțăm la un fișier. */
    var BUCATA_TRIES = 5, BUCATA_BACKOFF = [1000, 3000, 6000, 12000, 20000];
    /* Peste atât nu mai păstrăm o copie în telefon pentru reluarea de
       mâine — ar ocupa cât filmul însuși. */
    var PRAG_PASTRARE = 300 * 1024 * 1024;

    function uid() { return Date.now().toString(36) + Math.random().toString(36).slice(2, 8); }

    /* Identificator hexazecimal pentru server (îl cere strict hex).
       Se salvează lângă fișier, ca reluarea de mâine să nimerească
       aceeași bucată de pe server. */
    function sidNou() {
      var b = new Uint8Array(16);
      if (window.crypto && window.crypto.getRandomValues) window.crypto.getRandomValues(b);
      else for (var i = 0; i < 16; i++) b[i] = Math.floor(Math.random() * 256);
      return Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
    }

    dropzone.addEventListener('click', function () { input.click(); });
    dropzone.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
    input.addEventListener('change', function () { adauga(input.files); input.value = ''; });
    ['dragenter', 'dragover'].forEach(function (ev) { dropzone.addEventListener(ev, function (e) { e.preventDefault(); dropzone.classList.add('peste'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { dropzone.addEventListener(ev, function (e) { e.preventDefault(); dropzone.classList.remove('peste'); }); });
    dropzone.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files) adauga(e.dataTransfer.files); });

    /* Aceeași poză aleasă de două ori în aceeași listă. O prindem aici,
       ca să nu urcăm degeaba; serverul verifică oricum și conținutul. */
    function dejaInLista(f) {
      for (var i = 0; i < coada.length; i++) {
        var it = coada[i];
        if (it.file && it.file.name === f.name && it.file.size === f.size
            && it.file.lastModified === f.lastModified) return true;
      }
      return false;
    }

    function marimeText(o) {
      var u = ['B', 'KB', 'MB', 'GB'], i = 0, n = o;
      while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
      return (n >= 10 || i === 0 ? Math.round(n) : Math.round(n * 10) / 10) + ' ' + u[i];
    }

    function adauga(fileList) {
      var sarite = 0, preaMari = [], nepotrivite = 0;
      Array.prototype.forEach.call(fileList, function (f) {
        /* Un fișier care nu e nici poză, nici film, era pur și simplu
           ignorat: invitatul alegea ceva și nu se întâmpla nimic, fără
           un cuvânt. Acum i se spune. */
        if (!esteImagine(f) && !esteVideo(f)) { nepotrivite++; return; }
        if (dejaInLista(f)) { sarite++; return; }

        /* Filmele se trimit așa cum sunt, deci le știm mărimea de pe acum.
           Le oprim aici: altfel urcau douăzeci de minute și abia la final
           aflau că nu încap. Pozele se micșorează în telefon înainte de
           trimitere, deci pe ele nu are rost să le măsurăm acum. */
        if (LIMITE.limita && esteVideo(f) && f.size > LIMITE.limita) {
          preaMari.push(f.name + ' (' + marimeText(f.size) + ')');
          return;
        }
        var item = { id: uid(), sid: sidNou(), file: f, blob: null, name: f.name, nume: '', mesaj: '', isVideo: esteVideo(f), status: 'queued', processed: false, persisted: false, ultimaEroare: null };
        item.row = rowFor(item, f);
        lista.appendChild(item.row);
        coada.push(item);
      });
      if (nepotrivite > 0) {
        toast(nepotrivite === 1
          ? 'Acel fișier nu e o poză sau un film, așa că nu l-am adăugat.'
          : 'Am lăsat deoparte ' + nepotrivite + ' fișiere care nu sunt poze sau filme.');
      } else if (sarite > 0) {
        toast(sarite === 1 ? 'O fotografie era deja în listă.' : 'Am sărit ' + sarite + ' fișiere care erau deja în listă.');
      }
      if (preaMari.length) {
        var lim = LIMITE.limitaText || marimeText(LIMITE.limita);
        aratatPreaMari(preaMari, lim);
      }
      actualizeazaButon();
    }

    /* Mesaj limpede, cu ce e de făcut — nu doar „prea mare". */
    function aratatPreaMari(nume, lim) {
      var vechi = document.getElementById('prea-mari');
      if (vechi) vechi.remove();
      var d = document.createElement('div');
      d.id = 'prea-mari'; d.className = 'alerta';
      d.innerHTML = '<strong>' + (nume.length === 1 ? 'Un film e prea mare' : nume.length + ' filme sunt prea mari') +
        '</strong> (limita e ' + esc(lim) + '):<br>' + esc(nume.join(', ')) +
        '<br><br>Filmează mai scurt, sau schimbă din setările telefonului calitatea pe 1080p în loc de 4K. ' +
        'Pozele nu sunt afectate.';
      lista.parentNode.insertBefore(d, lista);
    }

    function rowFor(item, previewBlob) {
      var rand = document.createElement('div'); rand.className = 'fisier-rand';
      var miniHtml;
      if (!item.isVideo && previewBlob && /^image\/(jpeg|png|gif|webp|bmp)$/i.test(previewBlob.type || '')) {
        miniHtml = '<img class="mini" src="' + URL.createObjectURL(previewBlob) + '" alt="">';
      } else {
        miniHtml = '<div class="mini" style="display:flex;align-items:center;justify-content:center;color:#BF9B4F">' +
          (item.isVideo
            ? '<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>'
            : '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-5-5L5 21"/></svg>') + '</div>';
      }
      rand.innerHTML = miniHtml +
        '<div class="meta"><div class="nume">' + esc(item.name) + '</div>' +
        '<div class="stare" role="status">În așteptare</div>' +
        '<div class="bara" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"' +
        ' aria-label="Progres încărcare ' + esc(item.name) + '"><i></i></div></div>';
      item.row = rand;
      return rand;
    }

    function setStare(item, text, progres) {
      if (!item.row) return;
      var s = item.row.querySelector('.stare'); var b = item.row.querySelector('.bara > i');
      if (s) s.textContent = text;
      if (b && progres != null) {
        var proc = Math.round(progres * 100);
        b.style.width = proc + '%';
        var bara = item.row.querySelector('.bara');
        if (bara) bara.setAttribute('aria-valuenow', String(proc));
      }
    }

    function actualizeazaButon() {
      var deUrcat = coada.filter(function (it) { return it.status !== 'done'; }).length;
      btn.disabled = deUrcat === 0 || poolRuleaza;
      btn.textContent = poolRuleaza ? 'Se încarcă…' : (deUrcat > 0 ? 'Încarcă în album (' + deUrcat + ')' : 'Încarcă în album');
    }

    /* upload o singură bucată, cu progres */
    function urca(item, onProgress) {
      return new Promise(function (resolve) {
        var fd = new FormData();
        fd.append('fisier', item.blob, item.name);
        if (item.poster) fd.append('poster', item.poster, 'poster.jpg');
        fd.append('nume', item.nume); fd.append('mesaj', item.mesaj);
        var xhr = new XMLHttpRequest(); xhr.open('POST', 'upload.php');
        if (xhr.upload) xhr.upload.onprogress = function (e) { if (e.lengthComputable) onProgress(e.loaded / e.total); };
        xhr.onload = function () { try { resolve(JSON.parse(xhr.responseText)); } catch (_) { resolve({ ok: false }); } };
        xhr.onerror = function () { resolve({ ok: false }); };
        xhr.ontimeout = function () { resolve({ ok: false }); };
        xhr.send(fd);
      });
    }

    /* ---------- încărcare pe bucăți (fișiere mari) ---------- */

    /* O singură cerere către upload-bucati.php */
    function cerereBucati(campuri, fisiere, onProgress) {
      return new Promise(function (resolve) {
        var fd = new FormData();
        Object.keys(campuri).forEach(function (k) { fd.append(k, campuri[k]); });
        (fisiere || []).forEach(function (f) { fd.append(f.camp, f.blob, f.nume); });
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'upload-bucati.php');
        if (onProgress && xhr.upload) {
          xhr.upload.onprogress = function (e) { if (e.lengthComputable) onProgress(e.loaded); };
        }
        xhr.onload  = function () {
          /* Găzduirea permite un număr limitat de procese deodată. Când e
             plin, răspunde 508 sau 503 — nu e o defecțiune, e „revino
             imediat". Îl deosebim, ca să așteptăm mai mult înainte de a
             reîncerca, în loc să împingem și mai tare într-un server plin. */
          if (xhr.status === 508 || xhr.status === 503 || xhr.status === 429) {
            resolve({ ok: false, ocupat: true });
            return;
          }
          try { resolve(JSON.parse(xhr.responseText)); } catch (_) { resolve(null); }
        };
        xhr.onerror = function () { resolve(null); };
        xhr.ontimeout = function () { resolve(null); };
        xhr.send(fd);
      });
    }

    /* Întreabă serverul de unde reluăm. null = nu s-a putut afla. */
    function stareServer(sid) {
      return cerereBucati({ actiune: 'stare', id: sid }, null, null)
        .then(function (r) { return (r && r.ok) ? r.primit : null; });
    }

    /* Trimite fișierul felie cu felie, continuând de unde a rămas. */
    function urcaPeBucati(item, onProgress) {
      return (async function () {
        var total = item.blob.size;

        var primit = await stareServer(item.sid);
        if (primit === null) return false;                 // serverul nu răspunde
        if (primit > total) primit = 0;                    // nepotrivire → o luăm de la capăt

        while (primit < total) {
          var pana   = Math.min(primit + BUCATA, total);
          var felie  = item.blob.slice(primit, pana);
          var bazaPr = primit;
          var rez = null;

          function trimiteFelia() {
            return cerereBucati(
              { actiune: 'bucata', id: item.sid, offset: bazaPr, total: total },
              [{ camp: 'bucata', blob: felie, nume: 'b' }],
              function (incarcat) { onProgress(Math.min(1, (bazaPr + incarcat) / total)); }
            );
          }

          /* fiecare bucată are reîncercările ei; dacă pică, nu pierdem
             decât bucata curentă, nu tot fișierul */
          for (var t = 0; t < BUCATA_TRIES; t++) {
            rez = await trimiteFelia();

            /* Serverul plin nu e o defecțiune, ci „revino imediat". Îl
               așteptăm separat, fără să consumăm încercările pentru
               probleme de rețea — dar nu la nesfârșit. */
            var ocupatDe = 0;
            while (rez && rez.ocupat && ocupatDe < 6) {
              ocupatDe++;
              setStare(item, 'Serverul e aglomerat — aștept puțin…', bazaPr / total);
              await pauza(8000 + Math.random() * 7000);   // împrăștiem revenirile
              rez = await trimiteFelia();
            }
            if (rez && rez.ocupat) rez = null;            // tot plin: tratăm ca eșec

            if (rez) break;
            if (t < BUCATA_TRIES - 1) {
              setStare(item, 'Conexiune slabă — reiau de unde am rămas…', bazaPr / total);
              await pauza(BUCATA_BACKOFF[Math.min(t, BUCATA_BACKOFF.length - 1)]);
            }
          }

          if (!rez) return false;                          // rețeaua a căzut de tot
          if (rez.desincronizat) { primit = rez.primit; continue; }  // ne resincronizăm
          if (!rez.ok) { item.ultimaEroare = rez.eroare || null; return false; }

          primit = rez.primit;
          onProgress(Math.min(1, primit / total));
        }

        /* Gata bucățile — cerem lipirea în album. */
        setStare(item, 'Se finalizează…', 1);
        var campuri = { actiune: 'finalizeaza', id: item.sid, name: item.name, nume: item.nume, mesaj: item.mesaj };
        var fis = item.poster ? [{ camp: 'poster', blob: item.poster, nume: 'poster.jpg' }] : null;
        var fin = await cerereBucati(campuri, fis, null);
        if (fin && fin.ok) { item.duplicat = !!fin.duplicat; item.moderare = !!fin.moderare; return true; }
        item.ultimaEroare = (fin && fin.eroare) ? fin.eroare : null;
        return false;
      })();
    }

    function urcaCuReincercare(item) {
      return (async function () {
        var peBucati = item.blob && item.blob.size > PRAG_BUCATI;
        for (var t = 0; t < MAX_TRIES; t++) {
          var ok;
          if (peBucati) {
            ok = await urcaPeBucati(item, function (p) {
              setStare(item, 'Se încarcă… ' + Math.round(p * 100) + '%', p);
            });
            if (ok) return true;
          } else {
            var rez = await urca(item, function (p) { setStare(item, 'Se încarcă… ' + Math.round(p * 100) + '%', p); });
            if (rez && rez.ok) { item.duplicat = !!rez.duplicat; item.moderare = !!rez.moderare; return true; }
            item.ultimaEroare = (rez && rez.erori && rez.erori[0]) ? rez.erori[0] : null;
          }
          if (t < MAX_TRIES - 1) {
            setStare(item, 'Conexiune slabă — reîncerc…', 0);
            await pauza(BACKOFF[Math.min(t, BACKOFF.length - 1)]);
          }
        }
        return false;
      })();
    }

    function proceseazaItem(item) {
      return (async function () {
        if (item.processed) return;
        if (item.isVideo) { item.blob = item.file; item.poster = await posterVideo(item.file); item.processed = true; return; }
        var b = await proceseazaImagine(item.file);
        item.blob = b; if (b && b.name) item.name = b.name;
        item.processed = true;
      })();
    }

    function lucrator() {
      return (async function () {
        while (true) {
          var item = null;
          for (var i = 0; i < coada.length; i++) { if (coada[i].status === 'queued' || coada[i].status === 'pending') { item = coada[i]; break; } }
          if (!item) return;
          item.status = 'lucru';
          try {
            setStare(item, 'Se pregătește…', 0);
            await proceseazaItem(item);
            /* Pentru reluarea de mâine păstrăm o copie a fișierului în
               telefon. La filmele foarte mari nu are rost: copia ar
               dura mult și ar umple memoria telefonului degeaba. Acolo
               reluarea merge oricum cât timp pagina rămâne deschisă,
               pentru că serverul ține minte cât a primit. */
            if (item.blob && item.blob.size <= PRAG_PASTRARE) {
              try {
                await idbPut({ id: item.id, sid: item.sid, blob: item.blob, poster: item.poster || null,
                               name: item.name, nume: item.nume, mesaj: item.mesaj, isVideo: item.isVideo });
                item.persisted = true;
              } catch (e) { item.persisted = false; }
            } else {
              item.persisted = false;
            }
            var ok = await urcaCuReincercare(item);
            if (ok) {
              item.status = 'done'; item.row.classList.add('gata');
              setStare(item, item.duplicat ? 'Era deja în album ✓' : 'Încărcat ✓', 1);
              try { await idbDel(item.id); } catch (e) {}
            }
            else { item.status = 'error'; item.row.classList.add('eroare'); setStare(item, item.ultimaEroare || 'Nu s-a putut încărca — reia când revii', 0); }
          } catch (e) { item.status = 'error'; item.row.classList.add('eroare'); setStare(item, 'Eroare', 0); }
        }
      })();
    }

    function pornestePool() {
      if (poolRuleaza) return;
      poolRuleaza = true; actualizeazaButon();
      var w = []; for (var i = 0; i < CONC; i++) w.push(lucrator());
      Promise.all(w).then(function () { poolRuleaza = false; actualizeazaButon(); finalizeaza(); });
    }

    function finalizeaza() {
      var gata  = coada.filter(function (it) { return it.status === 'done'; });
      var done  = gata.length;
      var dubl  = gata.filter(function (it) { return it.duplicat; }).length;
      var noi   = done - dubl;
      var erori = coada.filter(function (it) { return it.status === 'error'; }).length;
      /* Când e un singur fișier, spunem ce este. „Fotografia ta a fost
         adăugată" după un film de 40 MB sună a greșeală, iar la nuntă se
         încarcă multe filme. La mai multe deodată ramane „fișiere", care
         e potrivit oricum ar fi amestecate. */
      var unNou  = noi  === 1 ? gata.filter(function (it) { return !it.duplicat; })[0] : null;
      var unVechi= dubl === 1 ? gata.filter(function (it) { return  it.duplicat; })[0] : null;

      /* Când mirii au pornit moderarea, fișierul NU e încă în album — îl
         văd ei întâi. Serverul spunea asta în răspuns, dar pagina nu se
         uita: invitatul era trimis într-o galerie unde poza lui nu era,
         și credea că s-a stricat ceva. */
      var subModerare = gata.some(function (it) { return it.moderare && !it.duplicat; });
      var eFilm = unNou && unNou.isVideo;

      if (done > 0 && erori === 0) {
        if (noi === 0) {
          succesTxt.textContent = dubl === 1
            ? (unVechi && unVechi.isVideo ? 'Acest film era deja în album.' : 'Această fotografie era deja în album.')
            : 'Toate cele ' + dubl + ' fișiere erau deja în album.';
        } else {
          var subiect = noi === 1 ? (eFilm ? 'Filmul tău' : 'Fotografia ta') : 'Cele ' + noi + ' fișiere';
          var urmare;
          if (subModerare) {
            urmare = noi === 1
              ? (eFilm ? ' a ajuns la miri și va apărea în album după ce îl văd. 🤍'
                       : ' a ajuns la miri și va apărea în album după ce o văd. 🤍')
              : ' au ajuns la miri și vor apărea în album după ce le văd. 🤍';
          } else {
            urmare = noi === 1
              ? (eFilm ? ' a fost adăugat în album.' : ' a fost adăugată în album.')
              : ' au fost adăugate în album.';
          }
          succesTxt.textContent = subiect + urmare
            + (dubl > 0 ? (dubl === 1 ? ' Unul era deja acolo.' : ' ' + dubl + ' erau deja acolo.') : '');
        }
        zona.style.display = 'none'; succes.style.display = 'block';
        succes.scrollIntoView({ behavior: 'smooth', block: 'center' });
      } else if (done > 0 && erori > 0) {
        toast(done + ' încărcate, ' + erori + ' rămase. Apasă „Reîncearcă".');
        adaugaButonReincercare(erori);
      } else if (erori > 0) {
        toast('Conexiune instabilă. Apasă „Reîncearcă".');
        adaugaButonReincercare(erori);
      }
    }

    function adaugaButonReincercare(n) {
      if (document.getElementById('btn-reincearca')) { document.getElementById('btn-reincearca').textContent = 'Reîncearcă cele ' + n + ' rămase'; return; }
      var b = document.createElement('button'); b.id = 'btn-reincearca'; b.className = 'btn btn-ghost btn-full'; b.style.marginTop = '12px';
      b.textContent = 'Reîncearcă cele ' + n + ' rămase';
      b.addEventListener('click', function () { coada.forEach(function (it) { if (it.status === 'error') { it.status = 'pending'; it.row.classList.remove('eroare'); } }); b.remove(); pornestePool(); });
      btn.parentNode.appendChild(b);
    }

    /* ---------- ține minte numele invitatului ----------
       Cine urcă poze de mai multe ori în seară nu trebuie să-și scrie
       numele de fiecare dată. Mesajul NU se reține: e legat de poza
       de atunci, nu de persoană. */
    var CHEIE_NUME = 'nunta_nume_invitat';
    var campNume = document.getElementById('nume');
    if (campNume) {
      try {
        var salvat = localStorage.getItem(CHEIE_NUME);
        if (salvat && !campNume.value) campNume.value = salvat;
      } catch (e) {}
    }

    btn.addEventListener('click', function () {
      var nume = (campNume ? campNume.value : '').trim();
      /* Câmpul de mesaj nu mai există în formular (gândurile se scriu în
         Cartea de urări), dar codul merge și dacă e pus la loc. */
      var campMesaj = document.getElementById('mesaj');
      var mesaj = campMesaj ? (campMesaj.value || '').trim() : '';
      try {
        if (nume) localStorage.setItem(CHEIE_NUME, nume);
        else localStorage.removeItem(CHEIE_NUME);
      } catch (e) {}
      coada.forEach(function (it) { if (it.status === 'queued') { it.nume = nume; it.mesaj = mesaj; } });
      pornestePool();
    });

    if (btnDinNou) btnDinNou.addEventListener('click', function () {
      coada = []; lista.innerHTML = '';
      var cm = document.getElementById('mesaj'); if (cm) cm.value = '';
      var br = document.getElementById('btn-reincearca'); if (br) br.remove();
      succes.style.display = 'none'; zona.style.display = 'block';
      actualizeazaButon(); window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    /* avertizare dacă se închide pagina în timpul încărcării */
    window.addEventListener('beforeunload', function (e) { if (poolRuleaza) { e.preventDefault(); e.returnValue = ''; } });

    /* RELUARE: dacă au rămas fișiere neîncărcate de data trecută */
    (function reia() {
      idbAll().then(function (rest) {
        if (!rest || !rest.length) return;
        var banner = document.createElement('div'); banner.className = 'banner-reluare';
        /* „1 fișier(e) neterminat(e)" e exact genul de text care sperie
           pe cineva care tocmai a pierdut netul. Îl scriem omenește. */
        banner.innerHTML = '<span>' + (rest.length === 1
            ? 'Ai un fișier rămas neterminat.'
            : 'Ai ' + rest.length + ' fișiere rămase neterminate.')
          + ' Continuăm automat de unde am rămas…</span>';
        var ren = document.createElement('button'); ren.className = 'btn btn-ghost btn-mic'; ren.textContent = 'Renunță';
        ren.addEventListener('click', function () { idbClear().then(function () { coada = coada.filter(function (it) { return it.status !== 'pending'; }); lista.innerHTML = ''; banner.remove(); actualizeazaButon(); }); });
        banner.appendChild(ren);
        zona.insertBefore(banner, zona.firstChild);
        rest.forEach(function (r) {
          var item = { id: r.id, sid: r.sid || sidNou(), file: null, blob: r.blob, poster: r.poster || null, name: r.name, nume: r.nume || '', mesaj: r.mesaj || '', isVideo: !!r.isVideo, status: 'pending', processed: true, persisted: true, ultimaEroare: null };
          item.row = rowFor(item, (r.isVideo && r.poster) ? r.poster : r.blob); lista.appendChild(item.row); coada.push(item);
        });
        actualizeazaButon(); pornestePool();
      }).catch(function () {});
    })();
  }

  /* ============================================================
     2) GALERIE + LIGHTBOX + APRECIERI
     ============================================================ */
  var galerieEl = document.getElementById('galerie');
  if (galerieEl) initGalerie();

  function initGalerie() {
    var sentinela = document.getElementById('sentinela');
    var miniLoader = document.getElementById('incarcare-mini');
    var golEl = document.getElementById('gol');
    var chips = document.querySelectorAll('.chip');

    var pagina = 0, seIncarca = false, maiSunt = true, niciUna = true, sortare = 'noi';
    var toate = [];

    var revealObs = new IntersectionObserver(function (intrari) { intrari.forEach(function (it) { if (it.isIntersecting) { it.target.classList.add('vizibil'); revealObs.unobserve(it.target); } }); }, { rootMargin: '80px' });

    function inimaSVG() { return '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>'; }

    function adaugaPoze(poze) {
      poze.forEach(function (p) {
        var idx = toate.length; toate.push(p);
        var div = document.createElement('div'); div.className = 'poza'; div.setAttribute('data-idx', idx); div.setAttribute('data-id', p.id);
        var inner = previzualizare(p, esc(p.nume || 'Fotografie de nuntă'));
        if (p.tip === 'video') inner += '<div class="play"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></div>';
        inner += '<button class="inima' + (esteApreciat(p.id) ? ' activ' : '') + '" data-like="' + p.id + '" aria-label="Apreciază">' + inimaSVG() + '<span>' + p.aprecieri + '</span></button>';
        if (p.nume || p.mesaj) inner += '<div class="pe-poza">' + (p.nume ? '<div class="nume">' + esc(p.nume) + '</div>' : '') + (p.mesaj ? '<div class="ms">' + esc(p.mesaj.length > 90 ? p.mesaj.slice(0, 90) + '…' : p.mesaj) + '</div>' : '') + '</div>';
        div.innerHTML = inner;
        div.addEventListener('click', function (e) { if (e.target.closest('.inima')) return; deschideLightbox(parseInt(div.getAttribute('data-idx'), 10)); });
        var bInima = div.querySelector('.inima');
        bInima.addEventListener('click', function (e) { e.stopPropagation(); comutaLike(p.id); });
        galerieEl.appendChild(div); revealObs.observe(div);
      });
    }

    function comutaLike(id) {
      var set = likeSet(); var nowLiked = !set.has(id); var val = nowLiked ? 1 : -1;
      var p = toate.find(function (x) { return x.id === id; });
      var vechiNumar = p ? p.aprecieri : 0;

      /* Arătăm imediat rezultatul, ca apăsarea să pară instantanee… */
      if (nowLiked) set.add(id); else set.delete(id);
      salveazaLike(set);
      if (p) p.aprecieri = Math.max(0, p.aprecieri + val);
      actualizeazaInimi(id);

      trimiteLike(id, val).then(function (r) {
        if (r && r.ok) {
          if (p) p.aprecieri = r.aprecieri;
          actualizeazaInimi(id);
          return;
        }
        /* …dar dacă serverul n-a reușit, dăm înapoi. Altfel inima rămânea
           apăsată degeaba, iar la reîncărcarea paginii sărea la loc. */
        var s = likeSet();
        if (nowLiked) s.delete(id); else s.add(id);
        salveazaLike(s);
        if (p) p.aprecieri = vechiNumar;
        actualizeazaInimi(id);
        toast((r && r.eroare) ? r.eroare : 'Aprecierea nu s-a putut salva.');
      });
    }

    function actualizeazaInimi(id) {
      var p = toate.find(function (x) { return x.id === id; }); if (!p) return; var liked = esteApreciat(id);
      galerieEl.querySelectorAll('.inima[data-like="' + id + '"]').forEach(function (b) { b.classList.toggle('activ', liked); var s = b.querySelector('span'); if (s) s.textContent = p.aprecieri; });
      if (lbIdActual === id) { lbLikeN.textContent = p.aprecieri; lbLike.classList.toggle('activ', liked); }
    }

    /* O cerere ratată NU înseamnă că albumul s-a terminat.
       Înainte, o singură pană de rețea în timpul derulării oprea galeria
       pentru totdeauna: invitatul vedea șaizeci de momente, fără niciun
       semn, și credea că ăsta e tot albumul — iar când revenea rețeaua,
       nu se relua nimic până la reîncărcarea paginii. */
    var reincercare = false;

    function aratEsec() {
      reincercare = true;
      miniLoader.style.display = 'block';
      miniLoader.innerHTML = 'Nu s-au putut aduce momentele următoare. ' +
        '<button type="button" class="btn btn-ghost btn-mic" id="btn-mai-multe" style="margin-left:8px">Încearcă din nou</button>';
      var b = document.getElementById('btn-mai-multe');
      if (b) b.addEventListener('click', function () { incarcaPagina(); });
    }

    function incarcaPagina() {
      if (seIncarca || !maiSunt) return Promise.resolve();
      seIncarca = true;
      if (reincercare) { reincercare = false; miniLoader.textContent = 'Se încarcă…'; }
      miniLoader.style.display = 'block';
      var esuat = false;
      return fetch('api.php?actiune=lista&sortare=' + sortare + '&pagina=' + (pagina + 1))
        .then(function (r) { if (!r.ok) throw new Error('raspuns ' + r.status); return r.json(); })
        .then(function (d) { if (d && d.ok) { pagina = d.pagina; if (d.poze.length) { niciUna = false; sincronizeazaLike(d.poze); adaugaPoze(d.poze); } maiSunt = d.maiSunt; } else { maiSunt = false; } })
        .catch(function () { esuat = true; })
        .finally(function () {
          seIncarca = false;
          if (esuat) { aratEsec(); return; }        // „maiSunt" rămâne, deci se poate relua
          miniLoader.style.display = 'none';
          if (niciUna) golEl.style.display = 'block';
          if (maiSunt && document.body.scrollHeight <= window.innerHeight + 200) incarcaPagina();
        });
    }

    function reseteaza() { pagina = 0; seIncarca = false; maiSunt = true; niciUna = true; toate = []; galerieEl.innerHTML = ''; golEl.style.display = 'none'; }

    chips.forEach(function (c) { c.addEventListener('click', function () { var s = c.getAttribute('data-sort'); if (s === sortare) return; sortare = s; chips.forEach(function (x) { x.classList.remove('activ'); }); c.classList.add('activ'); reseteaza(); incarcaPagina(); }); });

    new IntersectionObserver(function (e) { if (e[0].isIntersecting) incarcaPagina(); }, { rootMargin: '600px' }).observe(sentinela);

    /* Venit de pe banda de pe prima pagină: adresa poartă „#m123", adică
       „deschide-mi momentul acesta". Aici are voie să pornească filmul —
       pe prima pagină nu, ca să nu tragă nimic nimeni degeaba.
       Îl deschidem după ce s-a încărcat prima pagină de momente; banda
       arată cele mai noi, deci sunt toate acolo. */
    function deschideDinAdresa() {
      var m = /^#m(\d+)$/.exec(location.hash || '');
      /* Curățăm adresa oricum: dacă închide și reîncarcă pagina, nu vrem
         să-i sară din nou același fișier în față. */
      function curataAdresa() {
        if (history.replaceState) history.replaceState(null, '', location.pathname + location.search);
      }
      if (!m) return Promise.resolve();
      var id = parseInt(m[1], 10);

      function caută() {
        for (var i = 0; i < toate.length; i++) if (toate[i].id === id) return i;
        return -1;
      }

      /* Curățăm adresa ÎNAINTE de a deschide: deschiderea pune o intrare
         în istoric (ca „înapoi" să închidă poza), iar o curățare de după
         ar șterge tocmai acea intrare. */
      var i = caută();
      if (i >= 0) { curataAdresa(); deschideLightbox(i); return Promise.resolve(); }

      /* Nu e printre momentele încărcate — banda alege din tot albumul,
         deci poate fi de la începutul serii. Îl cerem punctual, într-o
         singură cerere, în loc să încărcăm galeria pagină cu pagină până
         la el. */
      return fetch('api.php?actiune=poza&id=' + id)
        .then(function (r) { return r.json(); })
        .then(function (d) {
          curataAdresa();                       // întâi adresa, apoi istoricul
          if (d && d.ok && d.poza) {
            sincronizeazaLike([d.poza]);
            toate.push(d.poza);
            deschideLightbox(toate.length - 1);
          }
        })
        .catch(curataAdresa);
    }

    incarcaPagina().then(deschideDinAdresa);

    /* ---- lightbox ---- */
    var lb = document.getElementById('lightbox'), lbCont = document.getElementById('lb-continut'), lbCap = document.getElementById('lb-caption'), lbDl = document.getElementById('lb-download');
    var lbLike = document.getElementById('lb-like'), lbLikeN = document.getElementById('lb-like-n');
    var lbSterge = document.getElementById('lb-sterge');
    var idxCurent = 0, lbIdActual = null;

    /* Numele cu care se salvează fișierul pe telefonul invitatului.
       Pe server are un nume aleatoriu; aici îi punem unul cu sens. */
    function numeDescarcare(p) {
      var ext = (p.original.split('.').pop() || 'jpg').split(/[?#]/)[0];
      var baza = (document.title.split('·').pop() || 'nunta').trim()
                  .replace(/\s+/g, '-').replace(/[^\w\-]/g, '').toLowerCase();
      return (baza || 'nunta') + '-' + p.id + '.' + ext;
    }

    /* Aducem din timp poza următoare și pe cea dinainte. Invitatul
       răsfoiește repede, iar fără asta fiecare pas așteaptă rețeaua de la
       zero. Browserul le ține în memoria lui, deci când ajunge la ele
       sunt deja acolo. Doar imagini: un film adus din timp ar trage zeci
       de MB pe care poate nu le deschide nimeni. */
    function preincarcaVecinii(i) {
      [i + 1, i - 1].forEach(function (k) {
        var v = toate[k];
        if (!v || v.tip === 'video') return;
        var im = new Image();
        im.decoding = 'async';
        im.src = v.original;
      });
    }

    function randeaza(i) {
      var p = toate[i]; if (!p) return; idxCurent = i; lbIdActual = p.id;
      lbCont.innerHTML = p.tip === 'video'
        ? '<video class="lb-media" src="' + esc(p.original) + '" controls autoplay playsinline></video>'
        : '<img class="lb-media" src="' + esc(p.original) + '" alt="">';

      /* Poza mare nu ajunge — pe wi-fi-ul sălii, un fișier de un megaoctet
         și jumătate poate pica. Fără asta, invitatul apăsa pe o poză și
         primea un ecran negru, fără un cuvânt. */
      if (p.tip !== 'video') {
        lbCont.querySelector('img').addEventListener('error', function () {
          if (lbIdActual !== p.id) return;
          lbCont.innerHTML =
            '<div class="lb-neredabil">' +
              '<svg viewBox="0 0 24 24" width="42" height="42" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><path d="M12 8v5"/><path d="M12 16.5v.01"/><circle cx="12" cy="12" r="9"/></svg>' +
              '<div class="titlu">Fotografia nu s-a putut încărca</div>' +
              '<p>Probabil a fost o clipă de conexiune proastă.</p>' +
              '<button type="button" class="btn btn-primar" id="lb-reincarca">Încearcă din nou</button>' +
            '</div>';
          var b = document.getElementById('lb-reincarca');
          if (b) b.addEventListener('click', function () { randeaza(idxCurent); });
        });
      }

      /* Un iPhone care filmează în 4K scoate un .mov pe care telefoanele
         Android și multe calculatoare nu îl pot deschide. Miniatura din
         grilă e făcută chiar de telefonul care a filmat, deci fișierul
         arată normal — iar la apăsare invitatul primea un dreptunghi
         negru, fără un cuvânt. Serverul nu poate schimba formatul, dar
         măcar spunem ce se întâmplă și dăm filmul la descărcat, unde
         aplicația telefonului îl deschide fără probleme. */
      if (p.tip === 'video') {
        lbCont.querySelector('video').addEventListener('error', function () {
          if (lbIdActual !== p.id) return;          // între timp a trecut mai departe
          lbCont.innerHTML =
            '<div class="lb-neredabil">' +
              '<svg viewBox="0 0 24 24" width="42" height="42" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><path d="M12 8v5"/><path d="M12 16.5v.01"/><circle cx="12" cy="12" r="9"/></svg>' +
              '<div class="titlu">Filmul nu se poate reda aici</div>' +
              '<p>A fost filmat într-un format pe care acest telefon nu îl deschide în pagină. ' +
              'Descarcă-l și îl vezi în aplicația ta de filme.</p>' +
              '<a class="btn btn-primar" href="' + esc(p.original) + '" download="' + esc(numeDescarcare(p)) + '">Descarcă filmul</a>' +
            '</div>';
        });
      }
      var cap = '';
      if (p.nume) cap += '<div class="nume">' + esc(p.nume) + '</div>';
      if (p.mesaj) cap += '<div class="mesaj">' + esc(p.mesaj) + '</div>';
      cap += '<div class="data">' + esc(p.data) + '</div>';
      lbCap.innerHTML = cap;
      lbDl.href = p.original;
      lbDl.setAttribute('download', numeDescarcare(p));
      lbLikeN.textContent = p.aprecieri; lbLike.classList.toggle('activ', esteApreciat(p.id));
      if (lbSterge) lbSterge.hidden = !p.alMeu;
      preincarcaVecinii(i);
    }

    /* Ștergerea propriului fișier. Serverul verifică din nou dreptul —
       aici doar nu arătăm butonul unde nu are ce căuta. */
    function stergeAlMeu(id) {
      var p = null, idx = -1;
      for (var i = 0; i < toate.length; i++) { if (toate[i].id === id) { p = toate[i]; idx = i; break; } }
      if (!p || !p.alMeu) return;
      var ce = p.tip === 'video' ? 'filmul' : 'fotografia';
      var confirmat = p.tip === 'video'
        ? 'Filmul a fost șters din album.'
        : 'Fotografia a fost ștearsă din album.';
      if (!window.confirm('Ștergi ' + ce + ' din album? Această acțiune nu poate fi anulată.')) return;

      var fd = new FormData(); fd.append('id', String(id));
      fetch('sterge-poza.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d || !d.ok) { toast((d && d.eroare) || 'Nu s-a putut șterge.'); return; }
          toate.splice(idx, 1);
          var el = galerieEl.querySelector('.poza[data-id="' + id + '"]');
          if (el) el.remove();
          /* indicii din DOM se schimbă după ștergere — le refacem */
          Array.prototype.forEach.call(galerieEl.querySelectorAll('.poza'), function (n, k) {
            n.setAttribute('data-idx', k);
          });
          inchide();
          toast(confirmat);
        })
        .catch(function () { toast('Conexiune întreruptă. Încearcă din nou.'); });
    }
    /* Focalizarea rămâne în lightbox cât e deschis: altfel tastatura
       „iese" pe legăturile din spatele lui, care nu se văd. */
    var focalizatInainte = null;
    function focalizabile() {
      return Array.prototype.filter.call(
        lb.querySelectorAll('button, a[href], video, [tabindex]:not([tabindex="-1"])'),
        function (el) { return !el.hasAttribute('hidden') && el.offsetParent !== null; }
      );
    }
    /* Pe telefon, „înapoi" e gestul cu care se închide orice e deschis.
       Fără asta, invitatul deschidea o poză, apăsa înapoi ca s-o închidă
       și ieșea din galerie cu totul — pierzând locul până unde derulase,
       după ce trecuse poate prin două sute de momente.

       Punem o intrare în istoric la deschidere, iar „înapoi" o consumă și
       închide doar poza. Închiderea din X, Escape sau apăsarea pe fundal
       trece prin aceeași ușă, ca istoricul să nu se umple cu intrări. */
    var intrareInIstoric = false;

    function deschideLightbox(i) {
      focalizatInainte = document.activeElement;
      randeaza(i);
      lb.classList.add('deschis'); lb.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
      if (!intrareInIstoric && window.history && history.pushState) {
        try { history.pushState({ lb: true }, ''); intrareInIstoric = true; } catch (e) {}
      }
      var f = focalizabile(); if (f.length) f[0].focus();
    }

    /* Închiderea propriu-zisă, fără să umble la istoric. */
    function inchideAcum() {
      lb.classList.remove('deschis'); lb.setAttribute('aria-hidden', 'true');
      lbCont.innerHTML = ''; lbIdActual = null; document.body.style.overflow = '';
      if (focalizatInainte && focalizatInainte.focus) focalizatInainte.focus();
      focalizatInainte = null;
    }

    /* Ce cheamă butoanele: dacă am pus o intrare în istoric, o scoatem —
       iar „popstate" face închiderea. Altfel închidem direct. */
    function inchide() {
      if (intrareInIstoric) { history.back(); return; }
      inchideAcum();
    }

    window.addEventListener('popstate', function () {
      intrareInIstoric = false;
      if (lb.classList.contains('deschis')) inchideAcum();
    });
    function navig(dir) {
      var nou = idxCurent + dir;
      if (nou < 0) nou = toate.length - 1;
      if (nou >= toate.length) { if (maiSunt) { incarcaPagina().then(function () { if (idxCurent + dir < toate.length) randeaza(idxCurent + dir); }); return; } nou = 0; }
      randeaza(nou);
    }
    document.getElementById('lb-inchide').addEventListener('click', inchide);
    document.getElementById('lb-prev').addEventListener('click', function (e) { e.stopPropagation(); navig(-1); });
    document.getElementById('lb-next').addEventListener('click', function (e) { e.stopPropagation(); navig(1); });
    lbLike.addEventListener('click', function (e) { e.stopPropagation(); if (lbIdActual != null) comutaLike(lbIdActual); });
    if (lbSterge) lbSterge.addEventListener('click', function (e) { e.stopPropagation(); if (lbIdActual != null) stergeAlMeu(lbIdActual); });
    lb.addEventListener('click', function (e) { if (e.target === lb || e.target === lbCont) inchide(); });

    /* ---- răsfoire cu degetul ----
       Aproape toți invitații sunt pe telefon, iar pe telefon gestul de a
       trece la poza următoare e trasul cu degetul, nu căutarea unei
       săgeți mici. Săgețile rămân, pentru cine e pe calculator.

       Nu prindem gestul început pe film (acolo degetul e pe bara de
       derulare) și nici pe butoane. Cerem o mișcare clar orizontală, ca
       să nu schimbăm poza când cineva doar trage pagina în jos. */
    var atX = 0, atY = 0, atT = 0, atActiv = false;
    var PRAG_SWIPE = 45;      // cât trebuie să meargă degetul, în puncte
    var PRAG_TIMP  = 800;     // peste atât nu mai e o trecere, e o apăsare lungă

    lb.addEventListener('touchstart', function (e) {
      if (e.touches.length !== 1) { atActiv = false; return; }
      if (e.target.closest('video, button, a')) { atActiv = false; return; }
      atX = e.touches[0].clientX; atY = e.touches[0].clientY;
      atT = Date.now(); atActiv = true;
    }, { passive: true });

    lb.addEventListener('touchend', function (e) {
      if (!atActiv) return;
      atActiv = false;
      var t = e.changedTouches && e.changedTouches[0];
      if (!t) return;
      var dx = t.clientX - atX, dy = t.clientY - atY;
      if (Date.now() - atT > PRAG_TIMP) return;
      if (Math.abs(dx) < PRAG_SWIPE) return;
      if (Math.abs(dx) < Math.abs(dy) * 1.5) return;   // mai mult vertical: e derulare
      navig(dx < 0 ? 1 : -1);
    }, { passive: true });
    document.addEventListener('keydown', function (e) {
      if (!lb.classList.contains('deschis')) return;

      /* Cât filmul e pe tot ecranul, tastele sunt ale lui: săgețile
         derulează, Escape iese din ecran complet. Fără asta, săgeata
         sărea la fișierul anterior chiar în timpul vizionării. */
      if (document.fullscreenElement || document.webkitFullscreenElement) return;

      /* La fel când filmul e selectat: derulează, nu schimbă fișierul. */
      var peFilm = e.target && e.target.tagName === 'VIDEO';

      if (e.key === 'Escape') { inchide(); return; }
      if (e.key === 'ArrowLeft')  { if (!peFilm) navig(-1); return; }
      if (e.key === 'ArrowRight') { if (!peFilm) navig(1);  return; }
      if (e.key !== 'Tab') return;

      /* Tab circulă doar printre elementele din lightbox. */
      var f = focalizabile();
      if (!f.length) { e.preventDefault(); return; }
      var primul = f[0], ultimul = f[f.length - 1];
      if (!lb.contains(document.activeElement)) { e.preventDefault(); primul.focus(); return; }
      if (e.shiftKey && document.activeElement === primul) { e.preventDefault(); ultimul.focus(); }
      else if (!e.shiftKey && document.activeElement === ultimul) { e.preventDefault(); primul.focus(); }
    });
  }

  /* Banda rulantă de pe prima pagină a fost scoasă: pozele recente se
     arată acum direct din PHP, fără încă o cerere la server. Codul ei nu
     mai avea de ce să se încarce pe telefonul fiecărui invitat. */
})();
