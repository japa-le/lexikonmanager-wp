# Lexikon Manager

Custom WordPress plugin zur Verwaltung von Lexikon-Einträgen inkl. Shortcodes, Suche (AJAX) und Ressourcen-Buttons (Video, Blog, Download, Infografik).

## Enthaltene Features

- Custom Post Type `lexikon`
- Metaboxen für Buchstabe, Kategorien (Tabs), Video-/Datei-/Bild-URL, Blog-Post-ID
- `lexikon.js` erweitert primär das Verhalten der bestehenden Elementor-Tabs (UI/Interaktion), statt ein eigenes Tab-System zu ersetzen
- Shortcodes:
  - `[lexikon_display type="verbraucher|regel|firmen"]`
  - `[lexikon_search type="verbraucher|regel|firmen"]`
  - `[verbraucher_search]`, `[regel_search]`, `[firmen_search]`, `[lexikon_global_search]`
- AJAX-Suche mit Nonce-Prüfung
- Quick Edit Unterstützung im Admin für Buchstabe und Insolvenz-Typ

## GitHub-bereinigte Version

Diese Version wurde für ein sauberes Repository vorbereitet:

- `import-lexikon-data.php` entfernt (nicht für Produktion/GitHub gedacht)
- `data.txt` entfernt (Import-Quelldatei)
- `.gitignore` ergänzt
- kleine Sicherheits-/Stabilitätsverbesserungen:
  - Capability-Check beim Speichern (`current_user_can('edit_post', $post_id)`)
  - Cache-Busting über `filemtime()` für JS-Dateien
  - Null-Check für `#lexikon-search` Event-Listener in `lexikon.js`
  - UI-Typo "Regelinsolvenz" korrigiert

## Installation

1. Plugin-Ordner nach `wp-content/plugins/` kopieren
2. In WordPress unter **Plugins** aktivieren
3. Shortcodes auf Seiten/Beiträgen verwenden