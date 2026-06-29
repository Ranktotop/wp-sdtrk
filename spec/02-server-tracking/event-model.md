# 02 — Event-Modell (`Wp_Sdtrk_Tracker_Event`)

Datei: `public/class-wp-sdtrk-tracker-event.php`.

Das Event-Objekt kapselt die vom Browser übergebenen Rohdaten (`$data['event']`) und stellt **normalisierte Getter** mit Fallbacks bereit. Alle Server-Tracker konsumieren ausschließlich diese Getter — nicht das Roh-Array.

## 0. Eingabe-Sanitisierung (in den Gettern)

Die Roh-Eventdaten stammen aus öffentlichem AJAX und sind nicht vertrauenswürdig. Die Getter sanitisieren daher **pro Feld** beim Auslesen, bevor Werte in Server-Payloads (cURL) oder `wp_localize_script` fließen:

| Feldtyp | Funktion | Getter |
|---------|----------|--------|
| Text/IDs | `sanitize_text_field()` | `getProductId`, `getProductName`, `getPageId`, `getPageName`, `getUserFirstName`, `getUserLastName`, `getBrandName`, `getTransactionId`, `getEventId`, `getEventAgent` |
| E-Mail | `sanitize_email()` | `getUserEmail` (ungültige → `''`) |
| URLs | `esc_url_raw()` | `getEventSource`, `getEventReferer`, `getEventUrl` |
| Betrag | `floatval()` | `getEventValue` |
| Positionsliste | `id`/`name` → `sanitize_text_field`, `qty` → `(int)`, `price` → `(float)` | `getItems` |

Saubere Werte bleiben unverändert (idempotent) — die Plattform-Payloads ändern sich für valides Input nicht. Das SHA256-Matching (Meta/TikTok) bleibt erhalten, da `sanitize_email()` gültige Adressen unangetastet lässt und das Hashing (lowercase/trim) erst im Tracker erfolgt.

Ergänzend härtet `validateTracker()` ([ajax-pipeline.md](ajax-pipeline.md)) den Dispatch: `type` wird auf `[a-z]` reduziert, `handler` gegen die Whitelist `Page|Event|Scroll|Time|Click|Visibility` geprüft, und die skalaren Werte des Seitenkanals `data.data` (fbp/fbc/cid/…) per `sanitize_text_field()` gesäubert.

## 1. Wichtige Getter

| Getter | Rückgabe | Beschreibung |
|--------|----------|--------------|
| `getEventName()` | string | Normalisierter Name (z. B. `purchase`, `view_item`) |
| `getEventValue()` | float | Monetärer Wert (0 wenn leer) |
| `getEventId()` | string | Eindeutige ID für Deduplizierung (Fallback: orderId / generiert) |
| `getTransactionId()` | string | Order-/Transaktions-ID |
| `getProductId()` / `getProductName()` | string | Produktdaten (erste Position) |
| `getItems()` | array | Strukturierte Positionsliste `[{id,name,qty,price}]` für Mehr-Produkt-Payloads; `[]` wenn nicht gesetzt |
| `getCurrency()` | string | Währung; Fallback `EUR` wenn leer |
| `getUserEmail()` | string | E-Mail (`sanitize_email`; Klartext, Hashing erst im Tracker) |
| `getUserFirstName()` / `getUserLastName()` | string | Namensdaten (`sanitize_text_field`, Klartext) |
| `getUserFingerprint()` | string\|false | Browser-Fingerprint |
| `getEventIp()` | string | Client-IP (Fallback `getClientIp()`) |
| `getEventAgent()` | string | User-Agent |
| `getEventSource()` / `getEventReferer()` / `getEventPath()` / `getEventDomain()` | string | URL-Kontext |
| `getEventTime()` | int | Unix-Timestamp |
| `getBrandName()` | string | Markenname |
| `getUtmData()` | array | UTM-Parameter |
| `getLocalizedEventData()` | array | Vereinfachte Eventdaten |

## 2. Event-Namens-Normalisierung (`parseEventName()`)

Eingehende Namen werden auf ein kanonisches Set abgebildet. Die Tracker übersetzen diese kanonischen Namen anschließend in plattformspezifische Event-Namen (siehe jeweilige Plattform-Seite).

| Kanonisch | Aliase (Eingang) |
|-----------|------------------|
| `view_item` | viewitem, viewcontent |
| `generate_lead` | generatelead, lead, submitform |
| `sign_up` | signup, completeregistration, doi |
| `add_to_cart` | addtocart, atc |
| `begin_checkout` | begincheckout, initiatecheckout |
| `purchase` | purchase, placeanorder, sale |

## 3. Trigger-Daten für Signal-Events

Für die Handler `Time`/`Scroll`/`Click`/`Visibility` setzt der Tracker zusätzliche Kontextdaten am Event:

```php
setTimeTriggerData($name, $id);
setScrollTriggerData($name, $id);
setClickTriggerData($name, $id, $tag);
setVisibilityTriggerData($name, $id, $tag);
```

Diese fließen u. a. in die plattformspezifische `event_id`-Bildung ein (siehe [user-data-deduplication.md](user-data-deduplication.md)).

## 4. Kanonische Event-Daten (vom Browser)

Das Roh-Event (`data.event`) enthält u. a. folgende Felder (gesammelt in `wp-sdtrk-event.js`):

```
eventId, orderId, eventName, prodId, prodName, value, currency,
items: [ { id, name, qty, price }, … ],   // ganzer Warenkorb (Mehr-Produkt)
userEmail, userFirstName, userLastName, userFP,
pageId, pageName, pageUrl,
utm: { utm_source, utm_medium, utm_term, utm_content, utm_campaign },
eventTime, eventTimeDay, eventTimeMonth, eventTimeHour,
eventSource, eventSourceAdress (IP), eventSourceAgent (UA), eventSourceReferer,
eventPath, eventDomain, eventUrl, brandName
```

> Quelle und Erfassungslogik dieser Felder: [03 › Event-Erfassung](../03-browser-tracking/event-collection.md).
