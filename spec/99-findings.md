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

## 🔴 Matomo: Tracker-Skript `matomo.js` wird nie geladen

**Verifiziert gegen die offizielle Doku.** `Wp_Sdtrk_Catcher_Mtm::loadPixel()` ([public/js/wp-sdtrk-mtm.js](../public/js/wp-sdtrk-mtm.js)) füllt die `_paq`-Queue (`setTrackerUrl`, `setSiteId`, `trackPageView`), **injiziert aber nicht** das Tracker-Skript `matomo.js`. Der offizielle Matomo-Embed verlangt zwingend zusätzlich die Script-Injection (`g.src = u + 'matomo.js'`, siehe [Matomo JS-Tracking-Guide](https://developer.matomo.org/guides/tracking-javascript-guide)). Ohne sie bleibt die Queue liegen und es wird **nichts** an Matomo gesendet — das Browser-Tracking feuert faktisch nicht. **Empfehlung:** in `loadPixel()` das `matomo.js`-Skript asynchron nachladen (wie bei den anderen Catchern).

---

## 🟡 Mautic: `mt('send', '<event>')` mit Custom-Event-Namen wird vom Standard-Mautic nicht unterstützt

**Verifiziert (Anbieter-Doku + Foren).** `Wp_Sdtrk_Catcher_Mtc::fireData()` sendet Events via `mt('send', '<eventName>', {…})`. Natives MauticJS ([mtc.js](https://devdocs.mautic.org/en/5.x/components/tracking_script.html)) dokumentiert nur `mt('send', 'pageview', {…})`; beliebige Event-Namen erfordern ein Zusatz-Plugin (z. B. „Mautic Custom Events"). Ohne dieses Plugin werden Nicht-`pageview`-Sends serverseitig vermutlich ignoriert. **Zu prüfen** an der echten Mautic-Instanz; ggf. dokumentieren, dass die Custom-Event-Erfassung das Plugin voraussetzt, oder auf `pageview` + Attribute umstellen.

---

## 🟡 Funnelytics: Base-Script (`track-v3.js` + `funnelytics.init`) nicht gegen die Anbieter-Doku verifiziert

Die Commerce-Implementierung von `Wp_Sdtrk_Catcher_Fl` ist **verifiziert korrekt**: Event-Name `__commerce_action__` und die Keys `__total_in_cents__` (in Cent), `__sku__`, `__order__`, `__currency__`, `__label__` entsprechen exakt dem offiziellen Vertrag ([Funnelytics — Base-Script + Revenue Actions](https://hub.funnelytics.io/c/tracking-setup/base-script-install)). Offen bleibt nur das **Base-Snippet**: `loadPixel()` lädt hartkodiert `https://cdn.funnelytics.io/track-v3.js` und ruft `funnelytics.init(funnel, false, deferredEvents)`; die Anbieter-Doku zeigt das Base-Script nicht (es wird account-spezifisch aus den Workspace-Settings kopiert). **Zu prüfen**, ob CDN-URL/`init`-Signatur noch dem aktuellen Workspace-Snippet entsprechen.

---

## 🟡 LinkedIn-Catcher: vergessenes `console.log` im Produktionscode

`Wp_Sdtrk_Catcher_Lin::get_triggeredConversions()` ([public/js/wp-sdtrk-lin.js](../public/js/wp-sdtrk-lin.js)) enthält ein unbedingtes `console.log(currentEventName)`, das bei jedem Event in die Browser-Konsole schreibt. (Die übrige LinkedIn-Integration — Insight-Tag-Snippet + `lintrk('track', { conversion_id })` — ist API-konform.) **Empfehlung:** entfernen.

---

## 🟡 Browser-only-Catcher: Währung hart `EUR`, single-product

Die reinen Browser-Catcher **Mautic** und **Funnelytics** setzen die Währung weiterhin hart auf `"EUR"` und tragen nur ein Produkt (kein Mehr-Produkt). Der Mehr-Produkt-/Shop-Währungs-Umbau betrifft nur die Kauf-Catcher Meta/GA/TikTok (siehe [07 › Purchase-Tracking](07-woocommerce/purchase-tracking.md)). Für Funnelytics empfiehlt die Doku bei mehreren Artikeln ausdrücklich **ein Event pro Artikel** (iterieren); der Catcher feuert derzeit nur ein `__commerce_action__` für die erste Position. **Bewerten**, ob für diese Plattformen relevant.

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

## 🟡 Produkt-Feed: Live-Generierung im Request-Pfad bei kaltem Cache

`Wp_Sdtrk_WC_Feed::get_cached()` liefert bei fehlendem Cache (Cron noch nicht gelaufen, Cache geleert) **synchron** einen Live-Aufbau über alle veröffentlichten Produkte (`collect()` mit `wc_get_products(['limit' => -1])` plus `wc_get_product()` je Variation) im öffentlichen — wenn auch token-geschützten — Request. Ein Halter des Token oder wiederholte Kalt-Cache-Treffer können so CPU/Speicher belasten; es gibt kein Batching, kein Rate-Limit und keinen Stampede-Schutz. **Empfehlung:** bei Cache-Miss `503`/leeren Feed liefern und Cron befüllen lassen, oder `generate()` mit einem kurzlebigen Transient-Lock absichern; ggf. `collect()` paginieren.

---

## 🟡 Produkt-Feed: Token in der URL, keine Rotation

Das Feed-Token wird als `?token=…`-Query-Parameter übertragen (von Google/Meta vorgegeben, die nur eine URL akzeptieren) und kann so in Server-/Proxy-/CDN-Logs landen. Es gibt aktuell **keine Rotations-Funktion**: `get_token()` erzeugt das Token einmalig; ein Reset ist nur durch Löschen der Option `wp_sdtrk_feed_token` in der DB möglich. **Empfehlung:** die Feed-URL als Geheimnis dokumentieren und einen Admin-Button zum Neu-Erzeugen des Tokens anbieten.

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
| 1 | Matomo: `matomo.js` wird nie geladen → Browser-Tracking feuert nicht | 🔴 hoch |
| 2 | Eingabe-Sanitisierung | 🟡 mittel |
| 3 | Mautic: Custom-Event-Sends ohne Plugin nicht unterstützt | 🟡 mittel |
| 4 | Funnelytics: Base-Script (`track-v3.js`/`init`) nicht verifiziert | 🟡 niedrig |
| 5 | Feed: Live-Generierung im Request-Pfad bei kaltem Cache | 🟡 mittel |
| 6 | Feed: Token in der URL, keine Rotation | 🟡 niedrig |
| 7 | LinkedIn: vergessenes `console.log` | 🟡 niedrig |
| 8 | Browser-only-Catcher (Mautic/Funnelytics): Währung hart `EUR`, single-product | 🟡 niedrig |
| 9 | Tote Stubs (Form-Handler etc.) | 🟡 niedrig |
| 10 | Keine Uninstall-Bereinigung | 🟡 niedrig |
| 11 | Namens-Inkonsistenzen | 🟡 niedrig |
