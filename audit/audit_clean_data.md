==== Verificari ====
1. Dezinstalat plugin - s-a sters tot - OK
2. Sters un form - s-au sters campurile ce mapau acel form toate - OK
3. Am sters un camp din form -> cand salvez maparea din nou pentru acel form - se sterge si acel camp din wp-options. Nu raman date orfane in tabela - OK

Dezinstalare / Stergere form / Curatare
Nume option	Conținut 
wpftab_api_url	URL API
wpftab_api_key	Cheie API
wpftab_debug_log_only	Mod debug: doar log, fără trimitere la API (1/0)
wpftab_last_debug_payload	Ultimul payload (JSON) afișat în pagina de setări; suprascris la fiecare submit în mod debug
wpftab_field_map	Mapare câmpuri formular (CF7)
wpftab_elementor_field_map	Mapare câmpuri formular (Elementor)
wpftab_utm_map	Mapare UTM
wpftab_custom_fields	Câmpuri custom (CF7)
wpftab_elementor_custom_fields	Câmpuri custom (Elementor)
wpftab_questions_answers	Questions & Answers (CF7)
wpftab_elementor_questions_answers	Questions & Answers (Elementor)
wpftab_cf7_name_field	Câmp full name (CF7)
wpftab_elementor_name_field	Câmp full name (Elementor)
wpftab_cf7_gdpr_fields	Câmpuri GDPR (CF7)
wpftab_cf7_marketing_fields	Câmpuri Marketing (CF7)
wpftab_elementor_gdpr_fields	Câmpuri GDPR (Elementor)
wpftab_elementor_marketing_fields	Câmpuri Marketing (Elementor)

Cum se gasesc în MySQL
Tabela are coloane tipice: option_id, option_name, option_value, autoload.

Toate opțiunile plugin-ului (prefix wpftab_):
SELECT option_name, option_value FROM wp_options WHERE option_name LIKE 'wpftab_%';

O opțiune anume, de ex. API URL:
SELECT option_name, option_value FROM wp_options WHERE option_name = 'wpftab_api_url';

Dacă ai alt table prefix (ex. myprefix_), înlocuiești wp_options cu myprefix_options:

SELECT option_name, option_value FROM myprefix_options WHERE option_name LIKE 'wpftab_%';

Rezumat: se salvează în tabela de options (wp_options sau {prefix}_options), cu prefix la numele opțiunii wpftab_, și se găsesc cu WHERE option_name LIKE 'wpftab_%'.