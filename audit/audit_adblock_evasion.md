# Măsuri pentru reducerea blocării de către AdBlock

Documentație a modificărilor făcute în plugin pentru a reduce șansa ca scriptul de setare a cookie-ului de sesiune (UTM / sursă trafic) să fie blocat de extensii tip AdBlock sau listele de blocare bazate pe nume/pattern.

---

## Scop

Scriptul setează cookie-ul `kmb_session_data` la prima vizită (parametri UTM, gclid/fbclid, referrer) pentru a fi citit la submit formular și trimis către API. Blocarea acestui script duce la lipsa atribuirii sursei de trafic. Măsurile de mai jos nu garantează evadarea, dar reduc semnalele tipice de „tracking” pe care le folosesc blocurile.

---

## Măsuri aplicate

### 1. Nume cookie neutru
- **Înainte:** `referrer_source` (cuvânt recunoscut în listele de blocare).
- **Acum:** `kmb_session_data` – neutru, asociat cu pluginul (Kingmaker Bridge), fără termeni „referrer” / „source” / „tracking”.

Folosit peste tot: în JS la setare, în PHP la citire (CF7, Elementor).

---

### 2. Script servit inline (fără URL extern)

- Codul JS **nu** este încărcat ca fișier extern (`<script src=".../traffic-cookie.js">`).
- Este injectat **inline** în pagină prin `wp_add_inline_script()`: conținutul fișierului este citit în PHP și atașat la un handle fără `src`.
- **Avantaj:** Blocurile care blochează pe baza URL-urilor de script (ex. `*traffic*cookie*.js`) nu au un URL de blocat; în HTML nu apare nicio adresă către acest script.

---

### 3. Nume fișier sursă neutru

- **Înainte:** `assets/traffic-cookie.js` („traffic” și „cookie” sunt termeni frecvenți în reguli de blocare).
- **Acum:** `assets/kmb-session.js` – nume generic, prefix `kmb-`, fără cuvinte tipice de tracking.

Fișierul este folosit doar pe server (citit cu `file_get_contents`); browserul nu încarcă acest URL. Redenumirea păstrează consistența și evitatul termenilor sensibili în codebase.

---

### 4. Handle WordPress neutru

- **Înainte:** `kingmaker-api-bridge-inline-handle`.
- **Acum:** `kmb-init` – scurt, neutru, fără „bridge” / „inline” / „tracking”.

Handle-ul poate apărea în atribute (ex. `id` pe tag-ul `<script>` sau în dependențe); un nume neutru reduce șansa de potrivire cu reguli generice.

---

### 5. Minificare și nume interne neutre

- **Fișier:** `assets/kmb-session.js` – o singură linie, fără comentarii.
- **Funcții/variabile:** nume scurte, neutre (ex. `_u`, `_s`, `_g`, `p`, `k`, `h`) în loc de `getUTMParameters`, `getTrafficSource`, `setReferrerSourceCookie`, `getCookie`.
- **Fără comentarii** în output.

Scop: reducerea pattern-urilor clare (nume de funcții / variabile) pe care unele blocuri le pot folosi pentru a identifica scripturi de „tracking” în conținutul inline.

---

## Ce nu s-a schimbat (comportament)

- **Numele câmpurilor din obiectul salvat în cookie** (ex. `traffic_source`, `utm_source`, `utm_medium`, …) – rămân aceleași; PHP citește același format în CF7 și Elementor.
- **Logica de determinare a sursei de trafic** – aceeași (gclid/fbclid/msclkid, UTM medium/source, referrer, „Direct”).
- **Perioada de expirare** – 30 zile, `path=/`, `SameSite=Lax`, `Secure` pe HTTPS.

---

## Limitări

- Blocurile pot folosi și **pattern-uri în conținut** (ex. stringuri `utm_source`, `gclid`, `document.cookie`). Minificarea și numele scurte reduc, dar nu elimină, acest risc.
- Dacă utilizatorul are JavaScript dezactivat, cookie-ul nu se setează (comportament neschimbat; varianta server-side a fost analizată și respinsă din cauza incompatibilității cu full-page cache).

---

## Fișiere afectate

| Fișier | Modificare |
|--------|------------|
| `kingmaker-api-bridge.php` | Cale către `kmb-session.js`, handle `kmb-init`, script inline |
| `assets/kmb-session.js` | Nou; cod minificat (înlocuiește `traffic-cookie.js`) |
| `assets/traffic-cookie.js` | Șters |
| `audit/audit_SECURITY.md` | Referință la `kmb-session.js` |
| `kingmaker-api-bridge-versions.txt` | Documentație handle / cale actualizate |

Integrările CF7 și Elementor citesc doar `$_COOKIE['kmb_session_data']`; nu depind de numele fișierului sau handle-ul scriptului.
