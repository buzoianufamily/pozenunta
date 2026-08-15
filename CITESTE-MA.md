# Album de nuntă — Răzvan & Maria · ghid (versiunea 2)

Aplicație de poze/filme pentru nuntă, pentru găzduire pe cPanel (RoMarg).
Domeniu: **nunta.razvanbuzoianu.ro** · Bază de date: **r140100buzo_pozenunta**.

---

## 1. Ce e nou în această versiune

- **Antetul pe mobil** reparat (R&M + meniul se așază corect, pe două rânduri centrate).
- **Fără limită practică la încărcare** — pozele se micșorează automat pe telefon (rămân instant), filmele pot ajunge până la ~1 GB/fișier.
- **Încărcare în paralel + reîncercare automată** dacă pică netul, și **reluare automată dacă se închide telefonul**: la redeschiderea paginii, fișierele neterminate se reiau singure (sunt salvate temporar în telefon). *Notă cinstită:* nu există încărcare „în fundal" cu telefonul închis (mai ales pe iPhone) — dar dacă invitatul redeschide pagina, continuă singură.
- **Buton apreciază (inimă)** pe fiecare poză + sortare „Cele mai noi / Cele mai apreciate".
- **Carte de urări** (pagină nouă cu toate mesajele invitaților).
- **Bandă cu cele mai noi poze** care se rotește pe prima pagină (apare doar după ce există poze).
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

În panou poți: vedea toate pozele, șterge/aproba, comuta moderarea, edita mesajul de bun venit, încărca/șterge **fotografia de cuplu**, **căuta după numele invitatului**, genera **codul QR** pentru invitați și descărca **tot albumul ca ZIP**.

Comutatorul de moderare: dacă e pornit, pozele invitaților NU apar în galerie până nu le aprobi tu. Acum e oprit (pozele apar instant). Îl lași așa dacă nu vrei să filtrezi.

---

## 5. Despre încărcare, viteză și 100 de invitați deodată

- **Pozele** se micșorează în telefon înainte de trimitere (rămân ~1-2 MB), deci se încarcă aproape instant. 100 de oameni care urcă poze în același timp — fără probleme; serverul (LiteSpeed) eliberează rapid fiecare proces.
- **Filmele** nu se comprimă. Singura limită reală e **spațiul pe disc** (acum 10 GB). Dacă se umple, **cumperi spațiu suplimentar** din cPanel/RoMarg — aplicația nu trebuie modificată.
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
- **Indicator de spațiu în panou:** o bară arată cât disc e folosit din total și cât a mai rămas liber. Pragul total se setează în `config.php` la `DISK_QUOTA_GB` (acum 10). **Când cumperi spațiu suplimentar, mărește această valoare** ca bara să fie corectă.
- **Imagine de partajare:** când trimiți linkul `nunta.razvanbuzoianu.ro` pe WhatsApp/Facebook, apar numele „Răzvan & Maria" și fotografia de cuplu (după ce o încarci din panou). Notă: WhatsApp/Facebook țin în cache previzualizarea; dacă ai trimis deja linkul înainte de a pune coperta, poate dura sau poți folosi un debugger de linkuri ca să reîmprospătezi previzualizarea.
- **Culori:** verdele de brand e acum uniform `#0D3328` peste tot (butoane, titluri), la fel ca bara de admin.
