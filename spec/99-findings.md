# 99 — Befunde & offene Punkte

Beobachtungen aus der Quellcode-Analyse (Stand v1.7.6). Eingeteilt nach Schweregrad. Dies ist eine **Ist-Zustand-Dokumentation** — die Punkte sind als Hinweise für die Weiterentwicklung gedacht, nicht als beauftragte Änderungen.

Legende: 🔴 funktionaler Bug · 🟡 Auffälligkeit/Hygiene · 🔵 Hinweis/Information

---

## 🔴 Meta-CAPI feuert serverseitig nicht {#meta-capi-dispatch}

**Verifiziert.** Der Browser-Meta-Catcher sendet `type: 'meta'` (`public/js/wp-sdtrk-meta.js`, `sendData()`):

```js
this.helper.send_ajax({ event: this.event, type: 'meta', handler, data }, …);
```

Der Server-Dispatch bildet daraus den Klassennamen (`public/class-wp-sdtrk-public-ajax.php`):

```php
$className = 'Wp_Sdtrk_Tracker_' . ucfirst($data['type']);   // → 'Wp_Sdtrk_Tracker_Meta'
if (class_exists($className)) { … }
```

Die tatsächliche Klasse heißt aber **`Wp_Sdtrk_Tracker_Fb`** (`public/class-wp-sdtrk-tracker-meta.php`, Zeile 3), und es gibt **kein** `class_alias`. Damit ist `class_exists('Wp_Sdtrk_Tracker_Meta') === false` und `validateTracker` liefert für Meta `state => false`.

**Folge:** Die Meta **Conversions API (Server-Tracking)** wird nie ausgelöst. GA4 (`Ga`) und TikTok (`Tt`) sind nicht betroffen, da dort Kürzel und Klassenname übereinstimmen. Der Meta-**Browser-Pixel** funktioniert unabhängig davon.

**Mögliche Lösungen:** Klasse in `Wp_Sdtrk_Tracker_Meta` umbenennen, oder `class_alias('Wp_Sdtrk_Tracker_Fb', 'Wp_Sdtrk_Tracker_Meta')` ergänzen, oder im Catcher `type: 'fb'` senden (Vorsicht: muss zur Datei-/Ladelogik passen).

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
- Datei `class-wp-sdtrk-tracker-meta.php` enthält Klasse `Wp_Sdtrk_Tracker_Fb` (siehe Meta-Bug oben).
- Decrypter-Klasse `Wp_Sdtrk_Decrypter_ds24` mischt CamelCase mit Kleinschreibung.

---

## 🟡 Tote / leere Bausteine

| Element | Beobachtung |
|---------|-------------|
| `Wp_Sdtrk_Public::register_front_end_routes()` | leerer Stub; an `init` gebunden, tut nichts |
| `Wp_Sdtrk_Public::load_custom_template()` | gibt Template unverändert zurück (No-Op) |
| `Wp_Sdtrk_Public_Form_Handler::handle_public_form_callback()` | Stub (returnt früh, keine Verarbeitung) |
| `WP_SDTRK_Cron::HOOKS` | leer → es werden **keine** Cron-Jobs geplant |
| `flush_rewrite_rules()` bei Aktivierung | ohne eigene Routen aktuell unnötig |
| `Wp_Sdtrk_Deactivator` Foreign-Key-Map | `$map = []` → wirkungslos |

> README erwähnt frühere Sync-Features (CSV-/Google-Sheet-/Live-Feed-Sync, „hourly"). Eine entsprechende aktive Cron-Nutzung ist im aktuellen Code **nicht** vorhanden.

---

## 🟡 Keine Daten-Bereinigung bei Deinstallation

`uninstall.php` enthält nur den Standard-Guard. Weder die Tabelle `{prefix}sdtrk_linkedin` noch die Option `wp_sdtrk_options` werden entfernt. Bei sauberer Deinstallation bleiben Daten dauerhaft zurück.

---

## 🔵 SHA256-Hashing ohne Salt — by design

E-Mail/Name werden mit reinem SHA256 (ohne Salt/HMAC) gehasht. Das ist **kein Bug**: Meta und TikTok verlangen exakt dieses Format, um die übermittelten Hashes mit ihren eigenen abzugleichen. Ein Salt würde das Matching verhindern. Dokumentiert wegen des Rainbow-Table-Themas bei E-Mail-Adressen.

---

## 🔵 Composer-PSR-4 verweist auf nicht vorhandenes `src/`

`composer.json` deklariert `Rankt\WpSmartServerSideTracking\` → `src/`, aber das Verzeichnis existiert nicht. Der Plugin-Code nutzt `require_once` statt Autoloading; der Namespace ist (derzeit) ungenutzt. Möglicher Hinweis auf eine geplante, nicht abgeschlossene Migration.

---

## 🔵 API-Versionen sind fest verdrahtet

- Meta: `graph.facebook.com/**v11.0**` (ältere Graph-API-Version).
- TikTok: `open_api/**v1.2**`.

Beide sind Kandidaten für ein Versions-Update; Payload-Strukturen sollten bei einer Anhebung gegen die aktuellen API-Verträge geprüft werden.

---

## Zusammenfassung (Priorität)

| # | Punkt | Schwere |
|---|-------|---------|
| 1 | Meta-CAPI Dispatch (`Fb` vs. `Meta`) | 🔴 hoch — Kernfunktion betroffen |
| 2 | Eingabe-Sanitisierung | 🟡 mittel |
| 3 | Tote Stubs / leere Cron | 🟡 niedrig |
| 4 | Keine Uninstall-Bereinigung | 🟡 niedrig |
| 5 | Namens-Inkonsistenzen | 🟡 niedrig |
| 6 | Veraltete API-Versionen | 🔵 beobachten |
