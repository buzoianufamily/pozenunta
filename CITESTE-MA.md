# Album de nuntă — Răzvan & Maria · ghid (versiunea 2)

Aplicație de poze/filme pentru nuntă, pentru găzduire pe cPanel (RoMarg).
Domeniu: **nunta.razvanbuzoianu.ro** · Bază de date: **r140100buzo_pozenunta**.

---

## 1. Ce e nou în această versiune

- **Antetul pe mobil** reparat (R&M + meniul se așază corect, pe două rânduri centrate).
- **Fără limită practică la încărcare** — pozele se micșorează automat pe telefon (rămân instant), filmele pot ajunge până la **4 GB/fișier** (`MAX_FILE_SIZE` în `config.php`).
- **Încărcare în paralel + reîncercare automată** dacă pică netul, și **reluare automată dacă se închide telefonul**: la redeschiderea paginii, fișierele neterminate se reiau singure (sunt salvate temporar în telefon). *Notă cinstită:* nu există încărcare „în fundal" cu telefonul închis (mai ales pe iPhone) — dar dacă invitatul redeschide pagina, continuă singură.
- **Buton apreciază (inimă)** pe fiecare poză + sortare „Cele mai noi / Cele mai apreciate".
- **Carte de urări** (pagină nouă cu toate mesajele invitaților).
- **Cele mai noi poze** apar direct pe prima pagină (doar după ce există poze).
- **Fotografie de cuplu (copertă)** pe prima pagină — o încarci tu din panou.
- **Căutare după numele invitatului** în panoul de administrare.
- **Butonul X** din vizualizatorul de poze a fost reparat.
- **Autentificarea adminului se face din `config.php`** (nu mai există „schimbă parola" în panou).
- Linkul „administrare" a dispărut din subsol (intri doar prin `login.php`).

---

## 2. Dacă ai deja site-ul instalat (ACTUALIZARE)

1. **Încarcă toate fișierele** din arhivă în `public_html` (folderul lui nunta.razvanbuzoianu.ro), suprascriind fișierele vechi. În cPanel: File Manager -> Upload arhiva, apoi Extract.
2. **Deschide `config.php`** și pune la loc datele tale (sunt doar câteva rânduri):
   - `DB_NAME`, `DB_USER`, `DB_PASS` — exact ca înainte;
   - `SITE_URL` — `https://nunta.razvanbuzoianu.ro`;
   - `ADMIN_USER` și **`ADMIN_PASS`** — utilizatorul și parola cu care intri în panou (acum se setează aici!).

   Atenție: dacă păstrezi vechiul `config.php`, autentificarea NU va merge, pentru că s-a redenumit `ADMIN_PASS_INITIALA` -> `ADMIN_PASS`. Cel mai sigur: folosește noul `config.php` și completează cele 5 valori.
3. **De obicei nu trebuie să rulezi nimic în baza de date** — la prima accesare aplicația își adaugă singură ce îi lipsește. Dacă totuși ceva nu merge, deschide `instalare.php` (cere autentificare) și îți spune exact ce lipsește și creează la apăsarea unui buton. Nu șterge niciodată date, deci poate fi rulat oricând.
4. **Reîmprospătează cu Ctrl+F5** (sau golește cache-ul) ca să se încarce noul design și noul cod.
5. Intră în panou (`login.php`), mergi la **Setări -> Fotografia de cuplu** și încarcă poza voastră de copertă (când o ai).

---

## 3. Instalare de la zero (dacă ar fi nevoie)

1. Urcă toate fișierele în `public_html`.
2. Editează `config.php`: datele bazei de date, `SITE_URL`, `ADMIN_USER`, `ADMIN_PASS`.
3. Intră pe `https://nunta.razvanbuzoianu.ro/login.php`, apoi deschide `instalare.php` și apasă butonul (creează tabelele).
4. Dacă mai ai pe server un `setup.php` din instalarea veche, **șterge-l** — nu cerea autentificare, deci îl putea deschide oricine. `instalare.php` îl înlocuiește și e protejat prin login.
5. Gata — pagina de start e `index.php`.

---

## 4. Cum intri ca administrator

- Mergi la `https://nunta.razvanbuzoianu.ro/login.php`.
- Utilizator și parolă = `ADMIN_USER` / `ADMIN_PASS` din `config.php`.
- Ca să schimbi parola, **editezi `config.php`** (rândul `ADMIN_PASS`).

În panou poți: vedea toate pozele, șterge/aproba (una câte una sau mai multe deodată), comuta moderarea, edita mesajul de bun venit, **schimba toate textele de pe pagini**, încărca/șterge **fotografia de cuplu**, **căuta după numele invitatului**, șterge sau aproba urări, deschide **editorul codului QR** și descărca **tot albumul ca ZIP**.

Comutatorul de moderare: dacă e pornit, pozele invitaților NU apar în galerie până nu le aprobi tu. Acum e oprit (pozele apar instant). Îl lași așa dacă nu vrei să filtrezi.

---

## 5. Despre încărcare, viteză și 100 de invitați deodată

- **Pozele** se micșorează în telefon înainte de trimitere (rămân ~1-2 MB), deci se încarcă aproape instant. 100 de oameni care urcă poze în același timp — fără probleme; serverul (LiteSpeed) eliberează rapid fiecare proces.
- **Filmele** nu se comprimă. Singura limită reală e **spațiul pe disc** (acum 80 GB). Dacă se umple, **cumperi spațiu suplimentar** din cPanel/RoMarg — aplicația nu trebuie modificată.
- Fiecare fișier se trimite separat, cu bară de progres; dacă pică netul, se reîncearcă automat, iar la redeschiderea paginii încărcarea se reia.

---

## 6. Codul QR pentru invitați

În panou -> **Cod QR pentru invitați** (`qr.php`). Îl printezi și îl pui pe mese; invitații îl scanează și ajung direct la pagina de încărcare.

---

## 7. Întreținere

- **Backup poze:** descarcă periodic ZIP-ul din panou sau copiază folderul `uploads/` prin FTP.
- **Spațiu:** dacă se apropie de plin (mai ales din filme), cumperi spațiu în plus.
- **Securitate:** `config.php` nu este accesibil din browser; parola o ții doar acolo.

Casă de piatră!

---

## 8. Adăugiri recente (2.1)

- **Miniatură (poster) pentru filme:** la încărcare, telefonul extrage primul cadru al filmului și îl trimite ca miniatură — în galerie filmele apar cu o imagine reală, nu cu un dreptunghi gol. (Dacă pe vreun telefon nu reușește, filmul tot se încarcă, doar fără poster.)
- **Indicator de spațiu în panou:** o bară arată cât disc e folosit din total și cât a mai rămas liber. Pragul total se setează în `config.php` la `DISK_QUOTA_GB` (acum 80). **Când cumperi spațiu suplimentar, mărește această valoare** ca bara să fie corectă.
- **Imagine de partajare:** când trimiți linkul `nunta.razvanbuzoianu.ro` pe WhatsApp/Facebook, apar numele „Răzvan & Maria" și fotografia de cuplu (după ce o încarci din panou). Notă: WhatsApp/Facebook țin în cache previzualizarea; dacă ai trimis deja linkul înainte de a pune coperta, poate dura sau poți folosi un debugger de linkuri ca să reîmprospătezi previzualizarea.
- **Culori:** verdele de brand e acum uniform `#0D3328` peste tot (butoane, titluri), la fel ca bara de admin.

---

## 9. Adăugiri mai noi

- **Invitatul își poate șterge singur** ce a pus — și pozele, și urarea — fără cont. E recunoscut după un cod pus în telefonul lui la prima încărcare; serverul verifică de fiecare dată, deci nimeni nu poate șterge ce a pus altcineva. Legătura e cu telefonul, nu cu persoana: de pe alt aparat nu-și mai vede butonul.
- **Aceeași poză pusă de două ori** e recunoscută și nu se dublează în album. Invitatului i se spune blând că era deja acolo.
- **Inimile se numără pe server**, câte una de invitat și poză — nu mai pot fi umflate din telefon și se văd la fel de pe orice aparat.
- **Filmele mari se trimit pe bucăți.** Dacă pică netul sau se închide telefonul, se reia exact din punctul rămas, nu de la zero.
- **În galerie se răsfoiește cu degetul** (tragi stânga/dreapta), iar pe telefon butoanele stau într-o bară sub fișier, ca să nu acopere filmul.
- **Un film pe care telefonul nu-l poate reda** (de obicei un `.mov` filmat cu iPhone-ul, deschis pe Android) arată un mesaj scurt și un buton de descărcare, în loc de un dreptunghi negru.
- **Adrese curate:** `/galerie` și `/urari` merg și fără `.php`.
- **Textele de pe pagini se schimbă din panou** — titluri, îndemnuri, etichetele câmpurilor, mesajele de mulțumire. Un buton le aduce pe toate înapoi la varianta de pornire.

## 10. Ce trebuie făcut înainte de nuntă

1. **Șterge fișierele de probă** din `uploads/` (păstrează `index.html`, `.htaccess` și folderul `thumbs`).
2. **Verifică pe telefonul tău real**, pe site-ul adevărat: urci o poză și un film, le deschizi în galerie, pui o inimă, ștergi ce ai pus, lași o urare. Dacă merg astea cinci, merge tot.
3. **Lasă moderarea oprită** în seara nunții, ca pozele să apară pe loc.
4. **Printează codul QR** ultimul, după ce ai terminat de umblat la el.
