# 99 — Befunde & offene Punkte

Beobachtungen aus der Quellcode-Analyse (Stand v1.7.6). Eingeteilt nach Schweregrad. Dies ist eine **Ist-Zustand-Dokumentation** — die Punkte sind als Hinweise für die Weiterentwicklung gedacht, nicht als beauftragte Änderungen.

Legende: 🔴 funktionaler Bug · 🟡 Auffälligkeit/Hygiene · 🔵 Hinweis/Information

---

## 🟡 Eingabe-Sanitisierung in der AJAX-Pipeline

**Verifiziert.** `validateTracker` übergibt `$_POST['data']` weitgehend ungefiltert an das Event-Modell:

```php
$event = new Wp_Sdtrk_Tracker_Event($data['event']);
```

Es findet keine durchgehende `sanitize_*()`-Behandlung statt. Nonce-Schutz ist vorhanden, eine Capability-Prüfung bewusst nicht (öffentliches Tracking). Werte fließen in Server-API-Payloads (cURL) und teils in `wp_localize_script` (dort JSON-encodiert → kein direktes XSS). **Empfehlung:** gezielte Sanitisierung (`sanitize_email`, `sanitize_text_field`, `floatval`) pro Feld; User-Agent vor Weiterverwendung escapen.

---

## 🟡 Namens-Inkonsistenzen

- Klassen-Präfix wechselt zwischen `Wp_Sdtrk_*` und `WP_SDTRK_*` (funktional egal, da PHP-Klassennamen case-insensitiv sind).
- Datei `class-wp-sdtrk-tracker-meta.php` enthält Klasse `Wp_Sdtrk_Tracker_Meta`; der historische Name `Wp_Sdtrk_Tracker_Fb` bleibt als `class_alias` bestehen.
- Decrypter-Klasse `Wp_Sdtrk_Decrypter_ds24` mischt CamelCase mit Kleinschreibung.

---

## 🟡 Tote / leere Bausteine

| Element | Beobachtung |
|---------|-------------|
| `Wp_Sdtrk_Public::register_front_end_routes()` | leerer Stub; an `init` gebunden, tut nichts |
| `Wp_Sdtrk_Public::load_custom_template()` | gibt Template unverändert zurück (No-Op) |
| `Wp_Sdtrk_Public_Form_Handler::handle_public_form_callback()` | Stub (returnt früh, keine Verarbeitung) |
| `flush_rewrite_rules()` bei Aktivierung | ohne eigene Routen aktuell unnötig |
| `Wp_Sdtrk_Deactivator` Foreign-Key-Map | `$map = []` → wirkungslos |

> Hinweis: `WP_SDTRK_Cron` ist seit der WooCommerce-Produkt-Feed-Funktion **aktiv** (täglicher Hook `wp_sdtrk_cron_generate_feed`, siehe [07 › Produkt-Feed](07-woocommerce/product-feed.md)). Die im README erwähnten früheren Sync-Features (CSV/Google-Sheet/Live-Feed) sind davon unabhängig und nicht implementiert.

---

## 🟡 Keine Daten-Bereinigung bei Deinstallation

`uninstall.php` enthält nur den Standard-Guard. Weder die Tabelle `{prefix}sdtrk_linkedin` noch die Option `wp_sdtrk_options` werden entfernt. Bei sauberer Deinstallation bleiben Daten dauerhaft zurück.

---

## 🟡 Währung fest auf `EUR` verdrahtet

Die Server-Tracker (Meta `getData_custom`/`fireTracking_Server_Event`, GA4, TikTok) sowie der Meta-Browser-Pfad (`get_data_custom`) setzen die Währung hart auf `"EUR"`. Für die [WooCommerce-Integration](07-woocommerce/README.md) liefert der [Order-Mapper](../public/class-wp-sdtrk-wc-order-mapper.php) zwar die Order-Währung (`currency`), diese wird aber von den Trackern noch nicht verwendet. Bei abweichender Shop-Währung wird die Conversion mit falscher Währung gemeldet. **Empfehlung:** Währung über das Event-Modell durchreichen und in den Payloads statt `EUR` verwenden.

---

## 🔵 SHA256-Hashing ohne Salt — by design

E-Mail/Name werden mit reinem SHA256 (ohne Salt/HMAC) gehasht. Das ist **kein Bug**: Meta und TikTok verlangen exakt dieses Format, um die übermittelten Hashes mit ihren eigenen abzugleichen. Ein Salt würde das Matching verhindern. Dokumentiert wegen des Rainbow-Table-Themas bei E-Mail-Adressen.

---

## 🔵 Composer-PSR-4 verweist auf nicht vorhandenes `src/`

`composer.json` deklariert `Rankt\WpSmartServerSideTracking\` → `src/`, aber das Verzeichnis existiert nicht. Der Plugin-Code nutzt `require_once` statt Autoloading; der Namespace ist (derzeit) ungenutzt. Möglicher Hinweis auf eine geplante, nicht abgeschlossene Migration.

---

## Zusammenfassung (Priorität)

| # | Punkt | Schwere |
|---|-------|---------|
| 1 | Eingabe-Sanitisierung | 🟡 mittel |
| 2 | Währung fest auf `EUR` (relevant für WooCommerce) | 🟡 mittel |
| 3 | Tote Stubs (Form-Handler etc.) | 🟡 niedrig |
| 4 | Keine Uninstall-Bereinigung | 🟡 niedrig |
| 5 | Namens-Inkonsistenzen | 🟡 niedrig |
