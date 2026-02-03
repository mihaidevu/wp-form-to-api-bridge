==== Verificari ====
1. Dezinstalat plugin - s-a sters tot - OK
2. Sters un form - s-au sters campurile ce mapau acel form toate - OK
3. Am sters un camp din form -> cand salvez maparea din nou pentru acel form - se sterge si acel camp din wp-options. Nu raman date orfane in tabela


Dezinstalare / Stergere form / Curatare
Nume option	Conținut 
wpftab_api_url	URL API
wpftab_api_key	Cheie API
wpftab_field_map	Mapare câmpuri formular
wpftab_utm_map	Mapare UTM
wpftab_custom_fields	Câmpuri custom

Cum se gasesc în MySQL
Tabela are coloane tipice: option_id, option_name, option_value, autoload.

Toate opțiunile plugin-ului (prefix wpftab_):
SELECT option_name, option_value FROM wp_options WHERE option_name LIKE 'wpftab_%';

O opțiune anume, de ex. API URL:
SELECT option_name, option_value FROM wp_options WHERE option_name = 'wpftab_api_url';

Dacă ai alt table prefix (ex. myprefix_), înlocuiești wp_options cu myprefix_options:

SELECT option_name, option_value FROM myprefix_options WHERE option_name LIKE 'wpftab_%';

Rezumat: se salvează în tabela de options (wp_options sau {prefix}_options), cu prefix la numele opțiunii wpftab_, și se găsesc cu WHERE option_name LIKE 'wpftab_%'.

