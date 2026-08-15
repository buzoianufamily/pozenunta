/* ============================================================
   BUZONNECT · Album nuntă — JavaScript (v2)
   - încărcare în paralel + reîncercare automată
   - reluare după închiderea telefonului (IndexedDB)
   - aprecieri (inimioare), sortare, galerie, lightbox, bandă
   ============================================================ */
(function () {
  'use strict';

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
  function salveazaLike(set) { try { localStorage.setItem(CHEIE_LIKE, JSON.stringify(Array.prototype.slice.call(set))); } catch (e) {} }
  function esteApreciat(id) { return likeSet().has(id); }

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
          try { var d = (v.duration && isFinite(v.duration)) ? v.duration : 2; v.currentTime = Math.min(1, d / 2); }
          catch (e) { termina(null); }
        });
        v.addEventListener('seeked', function () {
          try {
            var w = v.videoWidth || 600, h = v.videoHeight || 400;
            var scale = Math.min(1, 700 / Math.max(w, h));
            var c = document.createElement('canvas');
            c.width = Math.round(w * scale) || 600; c.height = Math.round(h * scale) || 400;
            c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
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
    var CONC = 3, MAX_TRIES = 4, BACKOFF = [800, 2000, 4000];

    /* Fișierele mai mari de atât se trimit pe bucăți, ca să poată fi
       reluate din punctul rămas. Pozele (micșorate pe telefon, ~1-2 MB)
       merg dintr-o bucată — e mai rapid și e drumul deja verificat. */
    var PRAG_BUCATI = 8 * 1024 * 1024;
    var BUCATA      = 4 * 1024 * 1024;
    var BUCATA_TRIES = 3, BUCATA_BACKOFF = [1000, 3000, 6000];

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

    function adauga(fileList) {
      var sarite = 0;
      Array.prototype.forEach.call(fileList, function (f) {
        if (!esteImagine(f) && !esteVideo(f)) return;
        if (dejaInLista(f)) { sarite++; return; }
        var item = { id: uid(), sid: sidNou(), file: f, blob: null, name: f.name, nume: '', mesaj: '', isVideo: esteVideo(f), status: 'queued', processed: false, persisted: false, ultimaEroare: null };
        item.row = rowFor(item, f);
        lista.appendChild(item.row);
        coada.push(item);
      });
      if (sarite > 0) {
        toast(sarite === 1 ? 'O fotografie era deja în listă.' : 'Am sărit ' + sarite + ' fișiere care erau deja în listă.');
      }
      actualizeazaButon();
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
        xhr.onload  = function () { try { resolve(JSON.parse(xhr.responseText)); } catch (_) { resolve(null); } };
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

          /* fiecare bucată are reîncercările ei; dacă pică, nu pierdem
             decât bucata curentă, nu tot fișierul */
          for (var t = 0; t < BUCATA_TRIES; t++) {
            rez = await cerereBucati(
              { actiune: 'bucata', id: item.sid, offset: bazaPr },
              [{ camp: 'bucata', blob: felie, nume: 'b' }],
              function (incarcat) { onProgress(Math.min(1, (bazaPr + incarcat) / total)); }
            );
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
        if (fin && fin.ok) { item.duplicat = !!fin.duplicat; return true; }
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
            if (rez && rez.ok) { item.duplicat = !!rez.duplicat; return true; }
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
            try { await idbPut({ id: item.id, sid: item.sid, blob: item.blob, poster: item.poster || null, name: item.name, nume: item.nume, mesaj: item.mesaj, isVideo: item.isVideo }); item.persisted = true; } catch (e) { item.persisted = false; }
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
      if (done > 0 && erori === 0) {
        if (noi === 0) {
          succesTxt.textContent = dubl === 1
            ? 'Această fotografie era deja în album.'
            : 'Toate cele ' + dubl + ' fișiere erau deja în album.';
        } else {
          succesTxt.textContent = (noi === 1 ? 'Fotografia ta a fost adăugată în album.' : 'Cele ' + noi + ' fișiere au fost adăugate în album.')
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
      var mesaj = (document.getElementById('mesaj').value || '').trim();
      try {
        if (nume) localStorage.setItem(CHEIE_NUME, nume);
        else localStorage.removeItem(CHEIE_NUME);
      } catch (e) {}
      coada.forEach(function (it) { if (it.status === 'queued') { it.nume = nume; it.mesaj = mesaj; } });
      pornestePool();
    });

    if (btnDinNou) btnDinNou.addEventListener('click', function () {
      coada = []; lista.innerHTML = '';
      document.getElementById('mesaj').value = '';
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
        banner.innerHTML = '<span>Avem ' + rest.length + ' fișier(e) neterminat(e). Continuăm automat de unde am rămas…</span>';
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
        var inner = '<img loading="lazy" src="' + esc(p.preview) + '" alt="' + esc(p.nume || 'Fotografie de nuntă') + '">';
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

    function incarcaPagina() {
      if (seIncarca || !maiSunt) return Promise.resolve();
      seIncarca = true; miniLoader.style.display = 'block';
      return fetch('api.php?actiune=lista&sortare=' + sortare + '&pagina=' + (pagina + 1))
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d && d.ok) { pagina = d.pagina; if (d.poze.length) { niciUna = false; sincronizeazaLike(d.poze); adaugaPoze(d.poze); } maiSunt = d.maiSunt; } else { maiSunt = false; } })
        .catch(function () { maiSunt = false; })
        .finally(function () { seIncarca = false; miniLoader.style.display = 'none'; if (niciUna) golEl.style.display = 'block'; if (maiSunt && document.body.scrollHeight <= window.innerHeight + 200) incarcaPagina(); });
    }

    function reseteaza() { pagina = 0; seIncarca = false; maiSunt = true; niciUna = true; toate = []; galerieEl.innerHTML = ''; golEl.style.display = 'none'; }

    chips.forEach(function (c) { c.addEventListener('click', function () { var s = c.getAttribute('data-sort'); if (s === sortare) return; sortare = s; chips.forEach(function (x) { x.classList.remove('activ'); }); c.classList.add('activ'); reseteaza(); incarcaPagina(); }); });

    new IntersectionObserver(function (e) { if (e[0].isIntersecting) incarcaPagina(); }, { rootMargin: '600px' }).observe(sentinela);
    incarcaPagina();

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

    function randeaza(i) {
      var p = toate[i]; if (!p) return; idxCurent = i; lbIdActual = p.id;
      lbCont.innerHTML = p.tip === 'video'
        ? '<video class="lb-media" src="' + esc(p.original) + '" controls autoplay playsinline></video>'
        : '<img class="lb-media" src="' + esc(p.original) + '" alt="">';
      var cap = '';
      if (p.nume) cap += '<div class="nume">' + esc(p.nume) + '</div>';
      if (p.mesaj) cap += '<div class="mesaj">' + esc(p.mesaj) + '</div>';
      cap += '<div class="data">' + esc(p.data) + '</div>';
      lbCap.innerHTML = cap;
      lbDl.href = p.original;
      lbDl.setAttribute('download', numeDescarcare(p));
      lbLikeN.textContent = p.aprecieri; lbLike.classList.toggle('activ', esteApreciat(p.id));
      if (lbSterge) lbSterge.hidden = !p.alMeu;
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
    function deschideLightbox(i) {
      focalizatInainte = document.activeElement;
      randeaza(i);
      lb.classList.add('deschis'); lb.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
      var f = focalizabile(); if (f.length) f[0].focus();
    }
    function inchide() {
      lb.classList.remove('deschis'); lb.setAttribute('aria-hidden', 'true');
      lbCont.innerHTML = ''; lbIdActual = null; document.body.style.overflow = '';
      if (focalizatInainte && focalizatInainte.focus) focalizatInainte.focus();
      focalizatInainte = null;
    }
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

  /* ============================================================
     3) BANDA cu cele mai noi poze (pagina de start)
     ============================================================ */
  var bandaTrack = document.getElementById('banda-track');
  if (bandaTrack) initBanda();

  function initBanda() {
    fetch('api.php?actiune=lista&sortare=noi&pagina=1').then(function (r) { return r.json(); }).then(function (d) {
      if (!d || !d.ok || !d.poze.length) return;
      var poze = d.poze.slice(0, 16);
      function el(p) {
        var a = document.createElement('a'); a.className = 'banda-item'; a.href = 'galerie.php';
        a.innerHTML = '<img loading="lazy" src="' + esc(p.preview) + '" alt="">' + (p.tip === 'video' ? '<span class="banda-play"><svg viewBox="0 0 24 24" fill="currentColor" width="26" height="26"><path d="M8 5v14l11-7z"/></svg></span>' : '');
        return a;
      }
      poze.forEach(function (p) { bandaTrack.appendChild(el(p)); });
      poze.forEach(function (p) { bandaTrack.appendChild(el(p)); }); // a doua copie pentru buclă lină
      bandaTrack.style.animationDuration = Math.max(18, poze.length * 2.4) + 's';
      document.getElementById('banda-sectiune').style.display = 'block';
    }).catch(function () {});
  }
})();
