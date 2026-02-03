# Raport securitate – WP Form to API Bridge

Analiză linie cu linie a fișierelor PHP din punct de vedere al securității (injection, XSS, CSRF, access control, expunere date).

---

## Concluzie generală

### Este plugin-ul sigur?

În configurația actuală, pentru utilizare normală (admin configurat corect, fără debug lăsat activ în producție), **da** – nivelul de securitate este bun:

- **Acces direct** la fișiere PHP este blocat (`ABSPATH` / `WP_UNINSTALL_PLUGIN`).
- **Admin:** doar utilizatori cu `manage_options` accesează setările; formularul are **CSRF** (nonce) și **sanitizare** pe input.
- **Front-end (CF7):** nu se afișează date din formular în HTML; nu există XSS din acest plugin. Nu există interogări SQL directe; se folosesc `get_option` / `update_option`.
- **API:** URL/headers din options; `sslverify => true` la `wp_remote_post`.

### Ce este deja bine verificat

- Protecție la acces direct (ABSPATH / WP_UNINSTALL_PLUGIN).
- Protecție CSRF pe toate formularele de setări (nonce).
- Defense in depth: verificare explicită `current_user_can('manage_options')` la salvare în `global-settings.php` și `form-mapping.php`.
- Sanitizare input (sanitize_text_field) la salvare setări și mapări.
- Escape la output (esc_attr, esc_html) în admin.
- form_id și indecși folosiți în logică sunt întregi (intval).
- Validare mapări față de câmpurile reale CF7.
- Fără interogări SQL construite din input utilizator.
- SSL verificat la apelurile către API.

### Ce s-ar mai putea îmbunătăți

1. **Debug (`integrations/cf7.php`)**  
   - Fișierul `debug-cf7.txt` conține date din formulare (și eventual căi din excepții) și poate fi accesibil din web.  
   - **Recomandare:**  
     - Să nu se scrie debug în producție (ex. constantă `WPFTAB_DEBUG` sau opțiune „Enable debug” doar pentru dezvoltare), **sau**  
     - Să se scrie într-un path în afara document root / protejat (ex. `.htaccess` deny sau reguli pe server).

---

## Analiză pe fișiere

### 1. `wp-form-to-api-bridge.php` (bootstrap plugin)

| Linie / zonă | Ce face | Verificare securitate |
|--------------|---------|------------------------|
| 10 | `if (!defined('ABSPATH')) exit;` | **OK** – Previne acces direct la fișier (evită include/require din URL). |
| 15–35 | `add_menu_page` / `add_submenu_page` cu `'manage_options'` | **OK** – Doar utilizatorii cu dreptul `manage_options` văd și accesează paginile. Capability-ul este verificat de WordPress. |
| 37–45 | `admin_enqueue_scripts` – verifică `$hook` | **OK** – Încarcă CSS doar pe paginile plugin-ului; nu există date din utilizator în path. |
| 47–55 | `wp_enqueue_script` pentru traffic-cookie.js | **OK** – URL generat din `plugin_dir_url(__FILE__)`, fără input utilizator. |
| 57–59 | `glob(plugin_dir_path(__FILE__) . 'integrations/*.php')` + `require_once $file` | **OK** – Calea este fixă (din `plugin_dir_path(__FILE__)`), fără input utilizator; se încarcă doar fișierele PHP din folderul plugin-ului. |

**Concluzie fișier:** Acces blocat fără WordPress, permisiuni admin corecte, fără XSS/CSRF aici.

---

### 2. `uninstall.php`

| Linie / zonă | Ce face | Verificare securitate |
|--------------|---------|------------------------|
| 2–4 | `if (!defined('WP_UNINSTALL_PLUGIN')) exit;` | **OK** – Rulează doar în contextul dezinstalării plugin-ului din WordPress. |
| 6–16 | Listă fixă de option names + `delete_option` / `delete_site_option` | **OK** – Nu există input utilizator; se șterg doar opțiunile cunoscute. Nu există risc de SQL injection (folosești API WordPress). |
| 18–21 | `plugin_dir_path(__FILE__) . 'debug-cf7.txt'`, `file_exists`, `unlink` | **OK** – Cale fixă, fără variabile din request. |

**Concluzie fișier:** Sigur; fără surse de atac în acest fișier.

---

### 3. `integrations/cf7.php`

| Linie / zonă | Ce face | Verificare securitate |
|--------------|---------|------------------------|
| 2 | `ABSPATH` check | **OK** – Acces direct blocat. |
| 4 | `WPCF7_VERSION` | **OK** – Doar verificare existență CF7. |
| 6–11 | `get_posts()` cu parametri fixi | **OK** – Fără input utilizator în query. |
| 14–22 | `$form_id` folosit în `get_instance($form_id)` | **OK** – `$form_id` vine din `$contact_form->id()` mai jos (linia 74), deci din obiectul CF7, nu direct din `$_GET`/`$_POST`. |
| 26–35 | `$tag->name`, `$tag->type`, `$tag->labels` în array | **OK** – Date din CF7; nu sunt afișate în HTML aici; folosite doar în admin (form-mapping cu escape). |
| 40–54 | Hook `wpcf7_mail_sent`, obiect + `WPCF7_Submission::get_instance()` | **OK** – Context valid CF7. |
| 51–54 | `$posted_data = $submission->get_posted_data()` | **OK** – Datele vin din CF7 (deja validate/sanitizate de CF7). Plugin-ul le trimite mai departe la API; nu le scrie în HTML, deci nu introduce XSS aici. |
| 56–62 | `$_COOKIE['referrer_source']` – decode JSON, folosit în `$cookie_data` | **OK** – Valorile din cookie sunt sanitizate cu `sanitize_text_field()` înainte de a fi puse în `$data`. |
| 64–72 | `get_option('wpftab_field_map'|'wpftab_utm_map')` | **OK** – Setări salvate de admin. |
| 74–77 | `$form_id = (int) $contact_form->id()` | **OK** – Integer, folosit ca index în array-uri. |
| 81–89 | `$posted_data`, `$field_map` – construiește `$data` | **OK** – Cheile și valorile sunt folosite pentru payload JSON și trimise la API. Nu sunt redate în HTML; `json_encode` previne breakarea formatului JSON. |
| 91–99 | `$utm_map`, `$cookie_data` – completează `$data` cu sanitizare | **OK** – Valorile din cookie sunt sanitizate cu `sanitize_text_field()` înainte de a fi puse în `$data`. |
| 99–105 | `get_option('wpftab_api_url'|'wpftab_api_key')` | **OK** – Din options, setate de admin. |
| 106–114 | `wpftab_custom_fields` – key/value sanitizate cu `sanitize_text_field` | **OK** – Input-ul din options (salvat din admin) e sanitizat. |
| 116–129 | Scriere în `debug-cf7.txt` – path din `plugin_dir_path(__FILE__)` | **Risc** – Fișierul este în `wp-content/plugins/wp-form-to-api-bridge/debug-cf7.txt`. Pe multe servere `wp-content` (sau doar `plugins`) poate fi servit pentru fișiere statice; dacă `.txt` este permis, **debug-cf7.txt poate fi descărcat** și conține date din formulare (inclusiv posibil date personale). **Recomandare:** dezactivare scriere debug în producție (ex. constantă sau opțiune), sau mutare fișier în afara document root / protejare prin .htaccess sau reguli server. |
| 146–148, 155–156, 161–162 | În catch: `$e->getMessage()`, `getFile()`, `getLine()` scrise în același fișier | **Aceeași problemă** – Fișierul de debug poate dezvălui căi de pe server. Să nu fie accesibil în producție. |
| 132–142 | `wp_remote_post($api_url, ...)` cu `sslverify => true` | **OK** – SSL verificat; URL și headers din options. |

**Concluzie fișier:** Logica de acces și trimitere la API este în regulă. Valorile din cookie sunt sanitizate. Punct sensibil rămas: **fișierul de debug** (locație și conținut) – să nu fie accesibil în producție.

---

### 4. `admin/global-settings.php`

| Linie / zonă | Ce face | Verificare securitate |
|--------------|---------|------------------------|
| 2 | `ABSPATH` check | **OK** – Acces direct blocat. |
| 4–16 | `get_option()` pentru api_url, api_key, utm_map | **OK** – Citire setări. |
| 17 | `$_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('wpftab_save_global', 'wpftab_nonce')` | **OK** – **CSRF** – Formularul este protejat cu nonce; request-uri POST fără nonce valid sunt respinse. |
| 18–23 | `sanitize_text_field($_POST['wpftab_api_url'|'wpftab_api_key'])`, `$_POST['wpftab_utm_map'][$key]` | **OK** – Toate input-urile POST sunt sanitizate înainte de salvare. |
| 31 | `wp_nonce_field('wpftab_save_global', 'wpftab_nonce')` | **OK** – Nonce inclus în formular. |
| 45, 56, 74, 83 | `esc_attr($api_url)` / `esc_attr($value)` / `esc_html($key)` | **OK** – **XSS** – Ieșirile în HTML sunt escape-uite corect. |
| 18–21 | La procesare POST: `if (!current_user_can('manage_options')) wp_die(...)` | **OK** – **Defense in depth** – Verificare explicită capability înainte de salvare; implementat. |

**Concluzie fișier:** CSRF și XSS tratate corect; input sanitizat; verificare explicită `current_user_can('manage_options')` la salvare (defense in depth).

---

### 5. `admin/form-mapping.php`

| Linie / zonă | Ce face | Verificare securitate |
|--------------|---------|------------------------|
| 2 | `ABSPATH` check | **OK** – Acces direct blocat. |
| 5 | `require_once plugin_dir_path(__FILE__) . '../integrations/cf7.php'` | **OK** – Cale fixă, fără variabile din request. |
| 11 | `$_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('wpftab_save_form_map', 'wpftab_nonce')` | **OK** – **CSRF** – Nonce pentru acțiunea de salvare. |
| 12–15 | La procesare POST: `if (!current_user_can('manage_options')) wp_die(...)` | **OK** – **Defense in depth** – Verificare explicită capability înainte de salvare; implementat. |
| 16 | `$form_id = intval($_POST['form_id'] ?? 0)` | **OK** – **Injection** – form_id forțat la integer. |
| 15–29 | `$_POST['wpftab_custom_fields']` – fiecare `key`/`value` cu `sanitize_text_field` | **OK** – Input sanitizat înainte de salvare. |
| 41–60 | `$_POST['wpftab_field_map']` – keys/values cu `sanitize_text_field` | **OK** – La fel. |
| 62–66 | Curățare: se șterg mapări pentru câmpuri care nu mai există în formularul CF7 | **OK** – Validare față de structura reală a formularului; reduce riscul de chei „rele” păstrate. |
| 87 | `$selected_form_id = ... intval($_GET['form_id'])` / `intval($_POST['form_id'])` | **OK** – form_id întotdeauna integer. |
| 117, 124, 126, 154–155, 157, 184–185, 193–194 | `esc_attr()`, `esc_html()` pe toate ieșirile în HTML | **OK** – **XSS** – Output escape-uit. |
| 184 | `name="wpftab_custom_fields[<?php echo $index; ?>][key]"` | **OK** – `$index` este integer (din count); nu e din utilizator. Pentru consistență poți folosi `esc_attr($index)`. |
| 239 | `fieldIndex` în JS din `count($form_custom_fields)` | **OK** – Valoare generată server-side, nu din input utilizator. |

**Concluzie fișier:** CSRF (nonce), injection (intval pe form_id), XSS (esc_*), validare mapări față de câmpurile CF7. Comportament sigur.