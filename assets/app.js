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
  function trimiteLike(id, val) {
    var fd = new FormData(); fd.append('id', id); fd.append('val', val);
    return fetch('like.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).catch(function () { return { ok: false }; });
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
    heicProm = new Promise(function (res, rej) { var s = document.createElement('script'); s.src = 'https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js'; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
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

    function uid() { return Date.now().toString(36) + Math.random().toString(36).slice(2, 8); }

    dropzone.addEventListener('click', function () { input.click(); });
    dropzone.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
    input.addEventListener('change', function () { adauga(input.files); input.value = ''; });
    ['dragenter', 'dragover'].forEach(function (ev) { dropzone.addEventListener(ev, function (e) { e.preventDefault(); dropzone.classList.add('peste'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { dropzone.addEventListener(ev, function (e) { e.preventDefault(); dropzone.classList.remove('peste'); }); });
    dropzone.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files) adauga(e.dataTransfer.files); });

    function adauga(fileList) {
      Array.prototype.forEach.call(fileList, function (f) {
        if (!esteImagine(f) && !esteVideo(f)) return;
        var item = { id: uid(), file: f, blob: null, name: f.name, nume: '', mesaj: '', isVideo: esteVideo(f), status: 'queued', processed: false, persisted: false, ultimaEroare: null };
        item.row = rowFor(item, f);
        lista.appendChild(item.row);
        coada.push(item);
      });
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
        '<div class="stare">În așteptare</div><div class="bara"><i></i></div></div>';
      item.row = rand;
      return rand;
    }

    function setStare(item, text, progres) {
      if (!item.row) return;
      var s = item.row.querySelector('.stare'); var b = item.row.querySelector('.bara > i');
      if (s) s.textContent = text;
      if (b && progres != null) b.style.width = Math.round(progres * 100) + '%';
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

    function urcaCuReincercare(item) {
      return (async function () {
        for (var t = 0; t < MAX_TRIES; t++) {
          var rez = await urca(item, function (p) { setStare(item, 'Se încarcă… ' + Math.round(p * 100) + '%', p); });
          if (rez && rez.ok) return true;
          item.ultimaEroare = (rez && rez.erori && rez.erori[0]) ? rez.erori[0] : null;
          if (t < MAX_TRIES - 1) { setStare(item, 'Conexiune slabă — reîncerc…', 0); await pauza(BACKOFF[Math.min(t, BACKOFF.length - 1)]); }
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
            try { await idbPut({ id: item.id, blob: item.blob, poster: item.poster || null, name: item.name, nume: item.nume, mesaj: item.mesaj, isVideo: item.isVideo }); item.persisted = true; } catch (e) { item.persisted = false; }
            var ok = await urcaCuReincercare(item);
            if (ok) { item.status = 'done'; item.row.classList.add('gata'); setStare(item, 'Încărcat ✓', 1); try { await idbDel(item.id); } catch (e) {} }
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
      var done = coada.filter(function (it) { return it.status === 'done'; }).length;
      var erori = coada.filter(function (it) { return it.status === 'error'; }).length;
      if (done > 0 && erori === 0) {
        succesTxt.textContent = done === 1 ? 'Fotografia ta a fost adăugată în album.' : 'Cele ' + done + ' fișiere au fost adăugate în album.';
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

    btn.addEventListener('click', function () {
      var nume = (document.getElementById('nume').value || '').trim();
      var mesaj = (document.getElementById('mesaj').value || '').trim();
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
        banner.innerHTML = '<span>Avem ' + rest.length + ' fișier(e) neîncărcat(e) de data trecută. Le reluăm automat…</span>';
        var ren = document.createElement('button'); ren.className = 'btn btn-ghost btn-mic'; ren.textContent = 'Renunță';
        ren.addEventListener('click', function () { idbClear().then(function () { coada = coada.filter(function (it) { return it.status !== 'pending'; }); lista.innerHTML = ''; banner.remove(); actualizeazaButon(); }); });
        banner.appendChild(ren);
        zona.insertBefore(banner, zona.firstChild);
        rest.forEach(function (r) {
          var item = { id: r.id, file: null, blob: r.blob, poster: r.poster || null, name: r.name, nume: r.nume || '', mesaj: r.mesaj || '', isVideo: !!r.isVideo, status: 'pending', processed: true, persisted: true, ultimaEroare: null };
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
      if (nowLiked) set.add(id); else set.delete(id); salveazaLike(set);
      var p = toate.find(function (x) { return x.id === id; }); if (p) p.aprecieri = Math.max(0, p.aprecieri + val);
      actualizeazaInimi(id);
      trimiteLike(id, val).then(function (r) { if (r && r.ok) { if (p) p.aprecieri = r.aprecieri; actualizeazaInimi(id); } });
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
        .then(function (d) { if (d && d.ok) { pagina = d.pagina; if (d.poze.length) { niciUna = false; adaugaPoze(d.poze); } maiSunt = d.maiSunt; } else { maiSunt = false; } })
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
    var idxCurent = 0, lbIdActual = null;

    function randeaza(i) {
      var p = toate[i]; if (!p) return; idxCurent = i; lbIdActual = p.id;
      lbCont.innerHTML = p.tip === 'video'
        ? '<video class="lb-media" src="' + esc(p.original) + '" controls autoplay playsinline></video>'
        : '<img class="lb-media" src="' + esc(p.original) + '" alt="">';
      var cap = '';
      if (p.nume) cap += '<div class="nume">' + esc(p.nume) + '</div>';
      if (p.mesaj) cap += '<div class="mesaj">' + esc(p.mesaj) + '</div>';
      cap += '<div class="data">' + esc(p.data) + '</div>';
      lbCap.innerHTML = cap; lbDl.href = p.original;
      lbLikeN.textContent = p.aprecieri; lbLike.classList.toggle('activ', esteApreciat(p.id));
    }
    function deschideLightbox(i) { randeaza(i); lb.classList.add('deschis'); lb.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden'; }
    function inchide() { lb.classList.remove('deschis'); lb.setAttribute('aria-hidden', 'true'); lbCont.innerHTML = ''; lbIdActual = null; document.body.style.overflow = ''; }
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
    lb.addEventListener('click', function (e) { if (e.target === lb || e.target === lbCont) inchide(); });
    document.addEventListener('keydown', function (e) { if (!lb.classList.contains('deschis')) return; if (e.key === 'Escape') inchide(); else if (e.key === 'ArrowLeft') navig(-1); else if (e.key === 'ArrowRight') navig(1); });
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
