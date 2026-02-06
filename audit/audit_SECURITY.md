# Raport securitate – KingMaker API Bridge

Analiză linie cu linie a fișierelor PHP din punct de vedere al securității (injection, XSS, CSRF, access control, expunere date).

---

## Concluzie generală

### Este plugin-ul sigur?

În configurația actuală, pentru utilizare normală (admin configurat corect), **da** – nivelul de securitate este bun:

- **Acces direct** la fișiere PHP este blocat (`ABSPATH` / `WP_UNINSTALL_PLUGIN`).
- **Admin:** doar utilizatori cu `manage_options` accesează setările; formularele au **CSRF** (nonce) și **sanitizare** pe input.
- **Front-end (CF7 + Elementor):** nu se afișează date din formular în HTML; nu există XSS din acest plugin. Nu există interogări SQL directe; se folosesc `get_option` / `update_option`.
- **API:** URL/headers din options; `sslverify => true` la `wp_remote_post`.

### Ce este deja bine verificat

- Protecție la acces direct (ABSPATH / WP_UNINSTALL_PLUGIN).
- Protecție CSRF pe toate formularele de setări (nonce).
- Defense in depth: verificare explicită `current_user_can('manage_options')` la salvare în `global-settings.php`, `form-mapping.php` și `form-mapping-elementor.php`.
- Sanitizare input (sanitize_text_field) la salvare setări și mapări.
- Escape la output (esc_attr, esc_html) în admin.
- form_id și indecși folosiți în logică sunt întregi (intval).
- Validare mapări față de câmpurile reale CF7 / Elementor.
- Fără interogări SQL construite din input utilizator.
- SSL verificat la apelurile către API.

### Ce s-ar mai putea îmbunătăți

Nu sunt riscuri critice rămase. Opțional, se poate adăuga logging minimal controlat de constantă pentru debugging în dev.

---

## Analiză pe fișiere

### 1. `kingmaker-api-bridge.php` (bootstrap plugin)

| Linie / zonă | Ce face | Verificare securitate |
|--------------|---------|------------------------|
| `ABSPATH` check | acces direct blocat | **OK** |
| `add_menu_page` / `add_submenu_page` cu `manage_options` | acces admin controlat | **OK** |
| `admin_enqueue_scripts` | CSS doar pe paginile plugin-ului | **OK** |
| `wp_enqueue_script` pentru traffic-cookie.js | URL generat intern | **OK** |
| `glob(.../integrations/*.php)` | include fișiere din plugin | **OK** |

**Concluzie fișier:** Acces blocat fără WordPress, permisiuni admin corecte.

---

### 2. `uninstall.php`

| Linie / zonă | Ce face | Verificare securitate |
|--------------|---------|------------------------|
| `WP_UNINSTALL_PLUGIN` check | rulează doar la uninstall | **OK** |
| listă fixă de options + `delete_option` / `delete_site_option` | șterge doar opțiuni cunoscute | **OK** |

**Concluzie fișier:** Sigur; fără surse de atac în acest fișier.

---

### 3. `integrations/cf7.php`

| Linie / zonă | Ce face | Verificare securitate |
|--------------|---------|------------------------|
| `ABSPATH` check | acces direct blocat | **OK** |
| `WPCF7_VERSION` check | rulează doar cu CF7 activ | **OK** |
| `get_posts()` cu parametri fixi | fără input utilizator | **OK** |
| `posted_data` + mapări | payload trimis la API | **OK** |
| `utm_map` + cookie | sanitize pe valori | **OK** |
| `wp_remote_post` cu `sslverify => true` | SSL verificat | **OK** |

**Concluzie fișier:** Logica de acces și trimitere la API este în regulă. Valorile din cookie sunt sanitizate.

---

### 4. `integrations/elementor.php`

| Linie / zonă | Ce face | Verificare securitate |
|--------------|---------|------------------------|
| `ABSPATH` check | acces direct blocat | **OK** |
| `ELEMENTOR_PRO_VERSION` / class_exists | rulează doar cu Elementor Pro activ | **OK** |
| mapări + payload | payload trimis la API | **OK** |
| `wp_remote_post` cu `sslverify => true` | SSL verificat | **OK** |

**Concluzie fișier:** Logica de acces și trimitere la API este în regulă.

---

### 5. `admin/global-settings.php`

| Linie / zonă | Ce face | Verificare securitate |
|--------------|---------|------------------------|
| `ABSPATH` check | acces direct blocat | **OK** |
| `check_admin_referer` | CSRF protecție | **OK** |
| `current_user_can('manage_options')` | access control | **OK** |
| `sanitize_text_field`, `esc_attr`, `esc_html` | sanitizare + output escaping | **OK** |

**Concluzie fișier:** CSRF și XSS tratate corect; input sanitizat.

---

### 6. `admin/form-mapping.php` / `admin/form-mapping-elementor.php`

| Linie / zonă | Ce face | Verificare securitate |
|--------------|---------|------------------------|
| `check_admin_referer` | CSRF protecție | **OK** |
| `current_user_can('manage_options')` | access control | **OK** |
| `sanitize_text_field`, `esc_attr`, `esc_html` | sanitizare + output escaping | **OK** |
| curățare mapări pentru câmpuri inexistente | prevenire date orfane | **OK** |

**Concluzie fișiere:** CSRF, XSS și injection tratate corect; comportament sigur.
