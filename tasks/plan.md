# Implementation Plan: ChatGPT Ads (OpenAI) Conversion-Tracking

## Overview

Das Plugin bekommt **OpenAI / ChatGPT Ads** als achte Tracking-Plattform. Sie ist wie Meta, GA4 und TikTok aufgebaut und bringt beide Wege mit:

- **Browser:** Measurement Pixel `oaiq` (lädt `https://bzrcdn.openai.com/sdk/oaiq.min.js`)
- **Server:** Conversions API (`POST https://bzr.openai.com/v1/events?pid=<PIXEL-ID>`, `Authorization: Bearer <API-KEY>`)

Beide Wege nutzen dieselbe `event_id`, damit OpenAI Browser- und Server-Event dedupliziert.

**Vorlage ist die TikTok-Integration** (`tt`): eine PHP-Tracker-Klasse, ein JS-Catcher, eine Redux-Subsektion. Die WooCommerce-Events Purchase, ViewItem, AddToCart und InitiateCheckout kommen automatisch dazu. Die WC-Integration füttert jeden Catcher mit den kanonischen Namen, und der neue `convert_eventname` muss sie nur abbilden.

Quellen, im Browser gelesen am 2026-09-21:
- [Measurement Pixel](https://developers.openai.com/ads/measurement-pixel)
- [Conversions API](https://developers.openai.com/ads/conversions-api)
- [Supported Events](https://developers.openai.com/ads/supported-events)

---

## Fakten aus der OpenAI-Doku, auf die sich der Plan stützt

| Thema | Dokumentierte Vorgabe |
|---|---|
| Pixel-Init | `oaiq("init", {pixelId, debug?, user?})`. `debug` schreibt SDK-Aktivität in die Konsole. |
| Pixel-Event | `oaiq("measure", <event>, <data>, <options?>)`. `options.event_id` dient der Dedupe, `options.custom_event_name` ist bei `custom` Pflicht. |
| Pixel-Consent | `oaiq("consent", false\|true)` vor `init`. Der Standard ist `true`. Blockierte Events werden nicht nachgesendet. |
| Pixel-Cookies | `__oppref`: Attributions-ID aus dem URL-Parameter `oppref`, 30 Tage, jeder neue Parameter setzt die Frist neu. `__obref`: zufällige Browser-Referenz, 365 Tage. |
| CAPI-Endpoint | `POST https://bzr.openai.com/v1/events?pid=<PIXEL-ID>`, Header `Authorization: Bearer <API-KEY>` |
| CAPI-Body | `{validate_only?, integration_source?, events:[…]}`, bis zu 1.000 Events. Scheitert ein Event, scheitert der ganze Batch. |
| CAPI-Event | `id` (Pflicht), `type` (Pflicht), `timestamp_ms` (Pflicht, Integer in ms, höchstens 7 Tage alt und höchstens 10 Minuten in der Zukunft), `source_url` (Pflicht bei `action_source: "web"`), `action_source`, `oppref`, `user`, `opt_out`, `data` (Pflicht) |
| CAPI-User | Plurale Listen: `emails_sha256[]`, `phone_numbers_sha256[]`, `external_ids_sha256[]`, `first_names_sha256[]`, `last_names_sha256[]`, `cities[]`, `regions[]`, `postal_codes[]`, `countries[]`. Einzelwerte: `obref` (roh), `ip_address`, `user_agent`. |
| `oppref` vs. `obref` | `oppref` steht auf Event-Ebene, `obref` in `user`. Die CAPI erfasst `oppref` **nicht selbst**, das Plugin muss den Wert erfassen und mitsenden. |
| Hashing | SHA-256, Ausgabe als 64 Hex-Zeichen in Kleinschrift. E-Mail vorher trimmen und kleinschreiben. Rohwerte dürfen nie gesendet werden. |
| Beträge | `amount` als **Integer in der ISO-4217-Minor-Unit** (Beispiel: `12999` für 129,99 USD). Mit `amount` ist `currency` Pflicht. |
| Dedupe | Schlüssel ist Pixel-ID + Event-Name (bei `custom` der `custom_event_name`) + `id` bzw. `event_id`. Das zuerst empfangene Event gewinnt. |
| Pixel-Einschränkung | `contents[].group_id` und `variant_dict` gibt es nur in der CAPI. `app_installed` und `app_opened` gibt es nur in der CAPI. |
| Doku-Hinweis | *„Do not call the server conversions API directly from page code.“* Der API-Key muss auf dem Server bleiben. Das erfüllt der bestehende AJAX-Weg. |

**Unterstützte Events:**

| Event | Datenform | Einsatz |
|---|---|---|
| `page_viewed` | `contents` | Seitenaufruf |
| `contents_viewed` | `contents` | Produkt oder Inhalt angesehen |
| `items_added` | `contents` | In den Warenkorb |
| `checkout_started` | `contents` | Checkout gestartet |
| `order_created` | `contents` | Kauf abgeschlossen |
| `lead_created` | `customer_action` | Lead |
| `registration_completed` | `customer_action` | Registrierung |
| `appointment_scheduled` | `customer_action` | Termin gebucht |
| `subscription_created` | `plan_enrollment` | Abo abgeschlossen |
| `trial_started` | `plan_enrollment` | Testphase gestartet |
| `custom` | `custom` | Alles andere |

---

## Architecture Decisions

1. **Plattform-Key `oai`, Options-Präfix `oai_`, Klasse `Wp_Sdtrk_Tracker_Oai`.**
   - Der AJAX-Dispatcher filtert `type` auf `[a-z]`, deshalb ist ein Key wie `openai` oder `oai` Pflicht und `chatgpt_ads` nicht möglich.
   - `oai` ist kurz und folgt dem Muster `tt`, `ga`, `lin`.
   - JS-Catcher: `public/js/wp-sdtrk-oai.js` mit der Klasse `Wp_Sdtrk_Catcher_Oai`, lokalisiertes Global `wp_sdtrk_oai`.

2. **Event-Mapping** (in PHP und JS identisch):

   | Kanonisch (Plugin) | OpenAI | `data.type` |
   |---|---|---|
   | Page-Handler | `page_viewed` | `contents` |
   | `view_item` | `contents_viewed` | `contents` |
   | `generate_lead` | `lead_created` | `customer_action` |
   | `sign_up` | `registration_completed` | `customer_action` |
   | `add_to_cart` | `items_added` | `contents` |
   | `begin_checkout` | `checkout_started` | `contents` |
   | `purchase` | `order_created` | `contents` |
   | Scroll, Time, Click, Visibility | `custom` + `custom_event_name` aus `evmap` (z. B. `scroll_depth_50`, `time_spent_30`) | `custom` |

   - Anders als bei TikTok gibt es mit `page_viewed` ein echtes Page-Event, statt auf ViewContent auszuweichen.
   - `appointment_scheduled`, `subscription_created` und `trial_started` haben kein kanonisches Gegenstück im Plugin und bleiben außen vor.

3. **Beträge in Minor Units.**
   - Das Plugin rechnet intern mit Float-Beträgen (`getEventValue()` → `floatval`). Neu kommt je ein Helper in PHP und JS dazu: `round(value × 10^exponent)`.
   - Der Exponent kommt aus einer kleinen Tabelle mit den Abweichungen von 2 Dezimalstellen: Währungen mit 0 Stellen (z. B. JPY, KRW) und mit 3 Stellen (z. B. KWD, BHD).
   - Die Liste der Währungen muss beim Umsetzen gegen die ISO-4217-Tabelle geprüft werden und darf nicht aus dem Gedächtnis kommen.
   - Die Regel gilt für `data.amount` und `contents[].amount`.

4. **Dedupe über die `event_id`.**
   - Die ID ist `grabOrderId()` bzw. `getEventId()`, also die Order-ID oder sonst die Engine-`eventId`.
   - Signal-Events hängen die bestehenden Suffixe an: `-s{percent}`, `-t{time}`, `-b{tag}`, `-v{tag}`.
   - Ein `_hash`-Suffix wie bei TikTok ist **nicht nötig**, weil OpenAI den Event-Namen in den Dedupe-Schlüssel aufnimmt. Page und Event dürfen daher dieselbe Basis-ID tragen.
   - Beide Seiten müssen dieselbe ID bilden. Das sichert ein Test ab.

5. **Click-ID `oppref` im Catcher selbst erfassen.**
   - Hintergrund: Die CAPI erfasst `oppref` nicht selbst. Ist nur der Server-Weg aktiv, lädt kein Pixel und es entsteht kein `__oppref`-Cookie.
   - **Erfassung:** Der Catcher liest den URL-Parameter `oppref` und legt ihn in einem eigenen Cookie ab. Die Laufzeit ist 30 Tage und wird wie beim Pixel bei jedem neuen Parameter neu gesetzt (analog `_ttc` / `fbc`).
   - **Fallback:** Fehlt der Parameter, liest der Catcher das Pixel-Cookie `__oppref`.
   - **`obref`:** kommt nur aus dem Pixel-Cookie `__obref`. Es existiert nur, wenn der Pixel geladen wurde, und wird ungehasht als `user.obref` gesendet.
   - **Übertragung:** Beide Werte gehen im AJAX-`data` an den Server, wie `ttc`/`ttp`.
   - Den Cookie-Namen legt T5 fest und nimmt ihn in die Spec auf.

6. **User-Daten nach dem bestehenden Minimierungsprinzip** (siehe Entscheidung zu Meta: kein `external_id`, keine Anreicherung aus Rechnungsdaten).
   - **Server:** `ip_address`, `user_agent`, `emails_sha256[]` (nur wenn eine E-Mail vorliegt), `obref`.
   - **Browser:** kein `user`-Objekt. Das Plugin kennt die E-Mail im Browser nur im Klartext, und der Pixel verlangt vorab gehashte Werte. Das ginge nur über das asynchrone `crypto.subtle` und nur in Secure Contexts.
   - Die „Automatic advanced matching“ von OpenAI läuft unabhängig davon im Pixel und wird in Ads Manager gesteuert.
   - Offene Frage 1 unten.

7. **Consent wie bei allen Plattformen.**
   - Der Pixel wird erst geladen, wenn Borlabs zustimmt (`b_cs`/`b_ci`). Der Server-Weg hat ein eigenes Gate (`s_cs`/`s_ci`).
   - Für späte Einwilligung gibt es Backload-Funktionen: `wp_sdtrk_backload_oai_b()` und `_s()`.
   - Die Consent-API des Pixels (`oaiq("consent", …)`) wird nicht gebraucht, weil der Pixel ohne Einwilligung gar nicht lädt.

8. **Debug und Test.**
   - `oai_trk_debug` setzt im Pixel `debug: true` und schaltet die Konsolen-Logs des Plugins ein.
   - Die CAPI hat keinen `test_event_code`. Das Gegenstück ist `validate_only: true`: Es prüft die Events, **speichert sie aber nicht**.
   - Deshalb gibt es einen eigenen Schalter `oai_trk_server_validate_only`, der nicht an `debug` gekoppelt ist. Sonst würden im Debug-Modus unbemerkt keine echten Conversions ankommen.

9. **`integration_source` wird nicht gesetzt.**
   - Laut Doku ist das Feld für Partner gedacht, die im Auftrag von Werbekunden senden. Es beeinflusst weder Authentifizierung noch Autorisierung.
   - Offene Frage 3.

10. **Keine eigenen WooCommerce-Hooks.**
    - Die bestehende WC-Integration liefert `purchase`, `begin_checkout`, `add_to_cart` und `view_item` über die Engine an jeden Catcher.
    - `contents[]` wird aus `items` gebaut: `id` ist die numerische WC-Produkt-ID wie im restlichen Tracking und im Feed, dazu `name`, `content_type: "product"`, `quantity` als Integer und `amount` in Minor Units.

---

## Dependency Graph

```
Admin-Optionen (Redux-Subsektion oai_*)
    │
    ├── Browser: registerScript + Localize (wp_sdtrk_oai) ──> Engine-Registrierung
    │       │
    │       └── JS-Catcher: loadPixel → page_viewed
    │               ├── Event-Mapping + contents + Minor-Unit-Helper (JS)
    │               ├── Signal-Events (custom)
    │               └── oppref/obref-Erfassung ──┐
    │                                            │ (AJAX data)
    └── Server: Bootstrap require + Tracker-Klasse (Dispatcher findet 'oai')
            ├── Payload-Builder + Minor-Unit-Helper (PHP) + Auth-Header
            ├── User-Daten (email hash, ip, ua, obref) + oppref  <──┘
            ├── Signal-Events (custom) → Dedupe-Parität mit JS
            └── validate_only + Fehlerauswertung
                    │
                    └── WooCommerce-Absicherung (Tests), Spec, i18n, Version/Changelog
```

---

## Task List

### Phase 1: Browser-Pixel (höchstes sichtbares Risiko: Laden, Consent, Dedupe-IDs)

#### Task 1: Pixel lädt und misst `page_viewed`
**Beschreibung:** Neue Redux-Subsektion „OpenAI / ChatGPT Ads“ mit den Browser-Feldern `oai_pixelid`, `oai_trk_debug`, `oai_trk_browser`, `oai_trk_browser_cookie_service` und `oai_trk_browser_cookie_id`, inklusive Borlabs-Snippet-Hinweis. Dazu `registerScript_oaiTracker()`, das Localize-Objekt `wp_sdtrk_oai` (`pid`, `b_e`, `b_cs`, `b_ci`, `s_e`, `s_cs`, `s_ci`, `dbg`), die Engine-Registrierung an allen fünf Trigger-Stellen und der Catcher mit `validate`, `loadPixel` (offizielles Snippet plus `init` mit `pixelId` und `debug`) und `fireData` für den Page-Handler.

**Akzeptanzkriterien:**
- [ ] Mit Pixel-ID und aktivem Browser-Tracking lädt der Pixel genau einmal. Der Guard `if (w.oaiq) return;` und `isPixelLoaded` greifen.
- [ ] Jeder Seitenaufruf ruft `oaiq("measure","page_viewed",{type:"contents",contents:[{id:<pageId>,name:<pageTitle>,content_type:"page"}]},{event_id:<eventId>})` auf.
- [ ] Ohne Borlabs-Consent lädt nichts. `wp_sdtrk_backload_oai_b()` holt das Laden und Messen nach. Admins werden nicht getrackt.

**Verifikation:**
- [ ] `node tests/test-oai-loadpixel.mjs` (neu, nach dem Muster von `test-mtm-loadpixel.mjs`)
- [ ] `node tests/test-nowc-regression.mjs` bleibt grün, OAI ist ergänzt.
- [ ] Manuell im lokalen WP: `debug: true` zeigt SDK-Logs in der Konsole, im Network-Tab erscheint ein Request an `bzr.openai.com`.

**Abhängigkeiten:** keine
**Dateien:** `admin/class-wp-sdtrk-admin.php`, `public/class-wp-sdtrk-public.php`, `public/js/wp-sdtrk-engine.js`, `public/js/wp-sdtrk-oai.js` (neu), `tests/test-oai-loadpixel.mjs` (neu)
**Umfang:** M

#### Task 2: Conversion-Events mit Beträgen und Contents (Browser)
**Beschreibung:** `convert_eventname` und `get_data_type` im Catcher nach Entscheidung 2. `get_data_custom` baut `amount` und `currency` in Minor Units sowie `contents[]` aus `items`, aus einem Einzelprodukt oder als Page-Fallback. Nur die im Pixel erlaubten Felder: `id`, `name`, `content_type`, `quantity`, `amount`, `currency`. Neuer JS-Helper `toMinorUnits(value, currency)`.

**Akzeptanzkriterien:**
- [ ] `purchase` mit `value = 25.99` und EUR ergibt `order_created` mit `{type:"contents",amount:2599,currency:"EUR",contents:[…]}` und `event_id = orderId`.
- [ ] `generate_lead` ergibt `lead_created` mit `{type:"customer_action"}`, ohne `contents`. Ein unbekanntes Event erzeugt keinen `measure`-Aufruf.
- [ ] JPY mit `value = 1500` ergibt `amount: 1500`. KWD mit `1.5` ergibt `1500`. `quantity` ist immer ein Integer.

**Verifikation:**
- [ ] `node tests/test-oai-custom-data.mjs` (neu, Muster `test-tt-custom-data.mjs`)

**Abhängigkeiten:** T1
**Dateien:** `public/js/wp-sdtrk-oai.js`, `tests/test-oai-custom-data.mjs` (neu)
**Umfang:** S

#### Task 3: Signal-Events als `custom` (Browser)
**Beschreibung:** Scroll, Time, Click und Visibility senden `oaiq("measure","custom",{type:"custom"},{custom_event_name:<evmap-Name>, event_id:<id+Suffix>})`. Der Name kommt aus `helper.get_EventName()` und muss den Regeln aus der Doku entsprechen: 1–64 Zeichen, `[A-Za-z0-9_-]`, Anfang und Ende alphanumerisch, keine Kollision mit einem Standardnamen.

**Akzeptanzkriterien:**
- [ ] 50 % Scroll ergibt `custom_event_name: "scroll_depth_50"` und `event_id: <eventId>-s50`.
- [ ] Ein ungültiger Name, etwa durch Sonderzeichen in einem Click-Tag, wird **nicht** gesendet, statt einen fehlerhaften Request zu erzeugen. Der Name wird aus dem Muster gebildet, der Tag landet nur in der ID. Dieses Verhalten beim Umsetzen gegen `button_click`/`item_visit` prüfen.

**Verifikation:**
- [ ] Erweiterung von `tests/test-oai-custom-data.mjs`

**Abhängigkeiten:** T2
**Dateien:** `public/js/wp-sdtrk-oai.js`, Test
**Umfang:** S

### Checkpoint A: Browser komplett
- [ ] Alle `node tests/test-*.mjs` grün
- [ ] Manueller Smoke-Test lokal mit der Test-Pixel-ID `4J4brGr1XUDTEKZhUQjeaa` und `debug: true`: Page, Lead und Scroll erscheinen in der Konsole
- [ ] Review mit Marc, bevor der Server-Teil beginnt

### Phase 2: Conversions API (Server)

#### Task 4: Server-Tracker für Page und Conversion-Events
**Beschreibung:** Neue Klasse `public/class-wp-sdtrk-tracker-oai.php` nach dem Muster von TikTok. `init` liest `oai_pixelid`, `oai_trk_server`, `oai_trk_server_token`, `oai_trk_debug` und `oai_trk_server_validate_only`. Endpoint mit `?pid=`, Header `Authorization: Bearer <token>`. Envelope `{validate_only, events:[event]}`. Event: `id`, `type`, `timestamp_ms` (Integer in ms), `source_url` (Seiten-URL), `action_source: "web"`, `data` wie in T2. Der PHP-Helper für Minor Units ist identisch zum JS-Helper. `require_once` im Bootstrap. Admin-Felder für den Server: Schalter, Token, Cookie-Service, Cookie-ID, Validate-only.

**Akzeptanzkriterien:**
- [ ] Ein AJAX-Aufruf mit `type=oai`, `handler=Event` und `purchase` erzeugt genau einen POST an `https://bzr.openai.com/v1/events?pid=<pid>` mit Bearer-Header und gültigem Body. Das prüft ein Payload-Test mit gestubbtem `do_post`.
- [ ] Ohne Token, Pixel-ID oder Server-Schalter wird kein Request gesendet.
- [ ] `source_url` hat immer Schema und Host, `timestamp_ms` ist ein Integer.

**Verifikation:**
- [ ] `php tests/test-oai-server-payload.php` (neu, Muster `test-tt-server-payload.php`)
- [ ] `php tests/test-ajax-dispatch.php` löst `oai` zu `Wp_Sdtrk_Tracker_Oai` auf.

**Abhängigkeiten:** T1 (Admin-Subsektion), T2 (Mapping-Parität)
**Dateien:** `public/class-wp-sdtrk-tracker-oai.php` (neu), `includes/class-wp-sdtrk.php`, `admin/class-wp-sdtrk-admin.php`, `tests/test-oai-server-payload.php` (neu), `tests/test-ajax-dispatch.php`
**Umfang:** M

#### Task 5: Attribution und Matching: `oppref`, `obref`, User-Daten
**Beschreibung:** Der Catcher erfasst `oppref` nach Entscheidung 5 in einem eigenen Cookie, liest `__obref` und schickt beides in `sendData` mit. Der Server setzt `oppref` auf Event-Ebene und das `user`-Objekt mit `ip_address`, `user_agent`, `obref` sowie `emails_sha256[]` (nur bei vorhandener E-Mail: trimmen, kleinschreiben, SHA-256). Leere Felder werden ausgelassen, nie als leerer String gesendet.

**Akzeptanzkriterien:**
- [ ] Die Landing-URL `?oppref=abc` ergibt ein Cookie, ein späterer Kauf ohne Parameter sendet serverseitig `"oppref":"abc"`. Die Frist von 30 Tagen wird bei jedem neuen Parameter neu gesetzt.
- [ ] Ist `__obref` vorhanden, wird es 1:1 als `user.obref` gesendet. Fehlt es, fehlt auch das Feld.
- [ ] Die E-Mail wird nie im Klartext gesendet. Ohne E-Mail gibt es kein `emails_sha256`.

**Verifikation:**
- [ ] `node tests/test-oai-user-data.mjs` (neu) und Erweiterung von `php tests/test-oai-server-payload.php`

**Abhängigkeiten:** T4
**Dateien:** `public/js/wp-sdtrk-oai.js`, `public/class-wp-sdtrk-tracker-oai.php`, `public/class-wp-sdtrk-public-ajax.php` (nur falls `sanitize_side_data` neue Keys kennen muss), Tests
**Umfang:** M

#### Task 6: Server-Signal-Events und Dedupe-Parität
**Beschreibung:** `fireTracking_Server_{Scroll,Time,Click,Visibility}` senden `type:"custom"`, `custom_event_name` und `data:{type:"custom"}` mit exakt derselben ID und demselben Namen wie der Browser in T3. Die Validierung des Namens ist identisch.

**Akzeptanzkriterien:**
- [ ] Für jeden Handler (Page, Event, Scroll, Time, Click, Visibility) sind `id` und Event-Name bzw. `custom_event_name` auf Browser- und Server-Seite identisch. Ein Test sichert das mit gemeinsamen Fixtures ab.

**Verifikation:**
- [ ] Payload-Test und JS-Test mit gleichen Eingaben vergleichen die erzeugten IDs und Namen.

**Abhängigkeiten:** T3, T4
**Dateien:** `public/class-wp-sdtrk-tracker-oai.php`, Tests
**Umfang:** S

#### Task 7: Fehlerauswertung und `validate_only`
**Beschreibung:** Das Fehlerformat der Antworten ist in den drei Doku-Seiten **nicht beschrieben**. `do_post` erkennt Fehler bisher nur an `$msg->error`. Beim ersten echten Aufruf (mit `validate_only: true`) wird die tatsächliche Antwort gegen die Test-Pixel-ID erfasst. Danach entscheidet sich, ob der Tracker einen HTTP-Code ungleich 2xx zusätzlich selbst als Fehler werten muss. `validate_only` hängt nur am eigenen Schalter.

**Akzeptanzkriterien:**
- [ ] Ein abgelehnter Request, etwa mit falschem Token oder einem Timestamp älter als 7 Tage, taucht mit aktivem `WP_DEBUG_LOG` als Fehler im Log auf und nicht als Erfolg.
- [ ] Mit `validate_only` an steht `"validate_only": true` im Body, sonst `false`.

**Verifikation:**
- [ ] Manueller CAPI-Aufruf mit Test-Pixel-ID und `validate_only: true`. **Dafür ist ein API-Key von Marc nötig, der Key wird nicht in den Code geschrieben.**
- [ ] Payload-Test für beide Schalterstellungen.

**Abhängigkeiten:** T4
**Dateien:** `public/class-wp-sdtrk-tracker-oai.php`, eventuell `includes/helpers/class-wp-sdtrk-helper-event.php`, Test
**Umfang:** S

### Checkpoint B: Server komplett
- [ ] Alle `php tests/test-*.php` und `node tests/test-*.mjs` grün
- [ ] Hybrid-Test lokal: Browser- und Server-Event für denselben Lead kommen mit gleicher ID an, per Konsole bzw. Log geprüft
- [ ] Review mit Marc

### Phase 3: WooCommerce, Spec, Release

#### Task 8: WooCommerce-Pfad absichern
**Beschreibung:** Keine neuen Hooks nötig. Die Tests zeigen, dass die vier WC-Events korrekt in OpenAI-Payloads übersetzt werden: `order_created` mit Order-ID als `id` und Summe in Minor Units, `checkout_started`, `items_added`, `contents_viewed`, jeweils mit numerischer Produkt-ID in `contents[].id`.

**Akzeptanzkriterien:**
- [ ] Die WC-Fixture einer Order mit 2 Positionen ergibt ein korrektes `order_created` mit zwei `contents` und Integer-Beträgen, im Browser wie auf dem Server.
- [ ] Ohne WooCommerce funktionieren alle OAI-Events unverändert (No-WC-Regression).

**Verifikation:**
- [ ] `php tests/test-wc-edge-cases.php`, `node tests/test-wc-engine-seeding.mjs` und `node tests/test-nowc-regression.mjs` sind um OAI erweitert und grün.

**Abhängigkeiten:** T2, T4
**Dateien:** Tests
**Umfang:** S

#### Task 9: Spec nachführen (Pflicht laut CLAUDE.md)
**Beschreibung:** Die Spec beschreibt den neuen Ist-Zustand, ohne Changelog-Sprache.

**Akzeptanzkriterien:**
- [ ] Neu: `spec/02-server-tracking/platform-openai.md` nach der Struktur von `platform-tiktok.md`.
- [ ] Ergänzt: Feature-Matrix und Glossar in `00-overview.md` (`oppref`, `obref`, Minor Unit); `02/README.md` (Plattform- und Vendor-Doku-Tabelle); `ajax-pipeline.md` §2.1; `user-data-deduplication.md`; `03/catchers.md`, `engine-and-lifecycle.md`, `cookies-fingerprint-decryption.md` (neues oppref-Cookie), `consent-management.md` (Backload-Funktionen); `04/option-reference.md`, `settings-and-menu.md`; `01/bootstrap-and-loader.md` §2.1, `directory-and-naming.md`; die Mapping-Tabellen in `07/purchase-tracking.md`, `view-item-and-add-to-cart.md`, `initiate-checkout.md`.
- [ ] Alle Indizes und Querverweise sind konsistent.

**Verifikation:**
- [ ] `grep -ri "tiktok\|\btt\b" spec/` durchgehen: Überall, wo alle Server-Plattformen aufgezählt werden, fehlt OpenAI nicht mehr.

**Abhängigkeiten:** T1–T8
**Dateien:** `spec/…` (viele kleine Edits, eine neue Datei)
**Umfang:** M (Dokumentation)

#### Task 10: i18n, Version, Changelog
**Beschreibung:** Neue Admin-Strings kommen in `languages/wp-sdtrk.pot` sowie in `de_DE.po`/`.mo`. Version auf 1.16.0 (neues Feature) in beiden Stellen von `wp-sdtrk.php`, in `README.txt` und in den Spec-Versionszeilen.

**Akzeptanzkriterien:**
- [ ] Die Admin-Oberfläche auf Deutsch zeigt die OAI-Subsektion vollständig übersetzt.
- [ ] Die Version ist an allen vier Stellen konsistent.

**Verifikation:**
- [ ] Manuell im Admin; `grep -n "1.16.0"`

**Abhängigkeiten:** T9
**Umfang:** S

### Checkpoint C: Release-bereit
- [ ] Alle Tests grün, `build-release.ps1` erzeugt das Zip, und `wp-sdtrk-oai.min.js` ist enthalten.
- [ ] Live-Smoke-Test: Pixel-Events im Ads Manager (Conversions-Tab) und CAPI-Events sichtbar, Dedupe greift.
- [ ] Freigabe durch Marc für Commit, Tag und Release.

---

## Risks and Mitigations

| Risiko | Auswirkung | Gegenmaßnahme |
|---|---|---|
| Fehlerantworten der CAPI sind nicht dokumentiert, und `do_post` wertet nur `error`-Keys aus | Hoch: abgelehnte Events bleiben unbemerkt | T7: echte Antwort mit `validate_only` erfassen, HTTP-Status zusätzlich auswerten |
| Beträge nicht in Minor Units, z. B. 25.99 statt 2599 | Hoch: falsche Conversion-Werte, Gebotsstrategie verzerrt | Eigener Helper, Tests mit EUR, JPY und KWD, Pixel und CAPI mit identischer Logik |
| Ohne Pixel kein `oppref`, das Server-Event ist nicht zuordenbar | Hoch: Server-only-Setups attribuieren kaum | T5: Catcher erfasst `oppref` selbst aus der URL |
| `validate_only` bleibt versehentlich an | Mittel: keine echten Conversions | Eigener Schalter mit deutlicher Beschreibung, nicht an Debug gekoppelt |
| Signal-Event-Namen verletzen die Custom-Name-Regeln | Mittel: ein ganzer Batch scheitert (hier je 1 Event) | Validierung vor dem Senden, im Browser und auf dem Server gleich |
| Pixel-SDK wird von OpenAI geändert (junges Produkt) | Mittel | Offizielles Snippet 1:1 übernehmen, die Doku-Links stehen in der Spec |
| API-Key landet im Frontend | Hoch | Token wird nur serverseitig gelesen und nie lokalisiert, das prüft ein Test gegen `wp_sdtrk_oai` |

---

## Open Questions (Entscheidung durch Marc)

> **Stand bei Freigabe (`/build auto`):** Die Empfehlungen werden übernommen.
> 1. Server sendet nur E-Mail-Hash, IP, UA und `obref`; der Browser sendet kein `user`-Objekt.
> 2. Signal-Events werden als `custom` gesendet.
> 3. `integration_source` wird weggelassen.
> 4. Admin-Titel ist „ChatGPT Ads (OpenAI)“.
> 5. Der Live-Test mit API-Key bleibt offen für Marc.

1. **User-Daten:** Wie bei TikTok nur die gehashte E-Mail plus IP, UA und `obref` auf dem Server (Empfehlung, passt zur Datenminimierung)? Oder zusätzlich gehashte Vor- und Nachnamen wie bei Meta, bzw. E-Mail-Hash im Browser über `crypto.subtle`?
2. **Signal-Events** (Scroll, Time, Click, Visibility) auch an OpenAI senden, wie bei den anderen Plattformen (Empfehlung: ja, als `custom`)? Oder nur echte Conversions?
3. **`integration_source`:** weglassen (Empfehlung, weil laut Doku für Partner gedacht) oder einen festen Wert wie `wp_sdtrk` setzen?
4. **Admin-Bezeichnung:** „ChatGPT Ads“ oder „OpenAI“? Technisch bleibt der Key `oai`.
5. **API-Key für Tests:** Für T7 und Checkpoint C braucht es einen Conversions-API-Key zur Test-Pixel-ID `4J4brGr1XUDTEKZhUQjeaa`. Hast du den schon in Ads Manager erzeugt?

---

## Nebenbefunde (nicht Teil dieses Plans)

- **Frontend-Debug-Flag:** `send_ajax` sendet es als `debug`, der AJAX-Handler liest `$_POST['meta']`. Die Server-Antwort wird deshalb nie ins Frontend gespiegelt. Das steht noch nicht in `spec/99-findings.md`.
- **Spec-Drift bei TikTok:** In `user-data-deduplication.md` und `cookies-fingerprint-decryption.md` stehen `context.ip` / `user_data.ttclid`, der Code nutzt `user.ip` / `user.ttclid`.
- **`SPEC.md` und `spec/README.md`** nennen noch Version 1.7.6.
