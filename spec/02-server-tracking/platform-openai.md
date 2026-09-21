# 02 — ChatGPT Ads (OpenAI) Conversions API

- **Klasse:** `Wp_Sdtrk_Tracker_Oai`
- **Datei:** `public/class-wp-sdtrk-tracker-oai.php`
- **`type`-Kürzel (Browser):** `oai`
- **Browser-Gegenstück:** `Wp_Sdtrk_Catcher_Oai` (`public/js/wp-sdtrk-oai.js`, Measurement Pixel `oaiq`, siehe [03 › Catcher](../03-browser-tracking/catchers.md))
- **Status:** ✅ funktionsfähig.

Maßgebliche Anbieter-Doku: [Measurement Pixel](https://developers.openai.com/ads/measurement-pixel) · [Conversions API](https://developers.openai.com/ads/conversions-api) · [Supported Events](https://developers.openai.com/ads/supported-events).

## Endpoint

```
POST https://bzr.openai.com/v1/events?pid={oai_pixelid}
Header: Authorization: Bearer {oai_trk_server_token}
        Content-Type: application/json
```

Ein Request transportiert genau ein Event im `events`-Array. Die API nimmt Batches bis 1.000 Events an; scheitert ein Event, scheitert der ganze Batch.

## Relevante Optionen

| Option | Bedeutung |
|--------|-----------|
| `oai_pixelid` | Pixel-ID (Conversions-Tab im ChatGPT Ads Manager) |
| `oai_trk_server` | Server-Tracking aktiv |
| `oai_trk_server_token` | Conversions-API-Key |
| `oai_trk_server_validate_only` | `validate_only: true` — OpenAI prüft die Events, **speichert sie aber nicht** |
| `oai_trk_debug` | Debug-Modus (Server-Log; Browser: Pixel-`debug` + Konsolen-Log) |

Server-Tracking ist nur aktiv, wenn Pixel-ID, `oai_trk_server` und Token gesetzt sind; sonst gibt `fireTracking_Server()` `true` zurück, ohne zu senden. Der Token wird nie in das Frontend lokalisiert.

## Event-Namens-Mapping (kanonisch → OpenAI)

| kanonisch / Handler | OpenAI `type` | `data.type` |
|---------------------|---------------|-------------|
| (page) | `page_viewed` | `contents` |
| `view_item` | `contents_viewed` | `contents` |
| `generate_lead` | `lead_created` | `customer_action` |
| `sign_up` | `registration_completed` | `customer_action` |
| `add_to_cart` | `items_added` | `contents` |
| `begin_checkout` | `checkout_started` | `contents` |
| `purchase` | `order_created` | `contents` |
| Scroll / Time / Click / Visibility | `custom` + `custom_event_name` | `custom` |

Nicht abgebildete Event-Namen erzeugen keinen Request. `appointment_scheduled`, `subscription_created`, `trial_started`, `app_installed` und `app_opened` haben kein kanonisches Gegenstück und werden nicht gesendet.

## Payload (Struktur)

```jsonc
{
  "validate_only": false,                    // true bei oai_trk_server_validate_only
  "events": [{
    "id": "<dedup-id>",                      // = pixel event_id
    "type": "order_created",
    "timestamp_ms": 1719424123000,           // Unix-Millisekunden (Integer)
    "source_url": "<url>",
    "action_source": "web",
    "oppref": "<oppref>",                    // optional, Event-Ebene
    "user": {                                // nur nicht-leere Felder
      "obref": "<__obref>",                  // ungehasht
      "emails_sha256": ["<sha256(lower(trim(email)))>"],
      "ip_address": "<ip>",
      "user_agent": "<ua>"
    },
    "data": {
      "type": "contents",
      "amount": 4900,                        // Minor Unit (Integer)
      "currency": "EUR",
      "contents": [{ "id": "<prodId>", "name": "<prodName>", "content_type": "product", "quantity": 1, "amount": 4900, "currency": "EUR" }]
    }
  }]
}
```

Signal-Event (Scroll/Time/Click/Visibility):

```jsonc
{ "id": "<basis>-s50", "type": "custom", "custom_event_name": "scroll_depth_50", …, "data": { "type": "custom" } }
```

`page_viewed` trägt `data: { "type": "contents", "contents": [{ "id": "<pageId>", "name": "<pageTitle>", "content_type": "page" }] }`.

## Besonderheiten

- **Beträge in Minor Units:** `amount` (Event und `contents[]`) ist ein Integer in der kleinsten Einheit nach ISO 4217: `round(Betrag × 10^Exponent)`. Exponent 0: BIF, CLP, DJF, GNF, ISK, JPY, KMF, KRW, PYG, RWF, UGX, UYI, VND, VUV, XAF, XOF, XPF. Exponent 3: BHD, IQD, JOD, KWD, LYD, OMR, TND. Exponent 4: CLF, UYW. Alle anderen Währungen: 2. Die identische Tabelle nutzt der Browser-Catcher.
- **`amount`/`currency`** werden gesetzt, wenn der Wert > 0 ist oder das Event `order_created` ist; Währung aus `getCurrency()` (Fallback `EUR`). Positionen mit Preis ≤ 0 tragen kein `amount`.
- **`contents[]`:** der ganze Warenkorb aus `items` (numerische WC-Produkt-ID als `id`), sonst das Einzelprodukt, sonst kein `contents`. `quantity` ist ein Integer ≥ 1. `customer_action`-Events tragen nie `contents`.
- **`timestamp_ms`:** Event-Zeit × 1000. Liegt die Client-Zeit außerhalb des von OpenAI akzeptierten Fensters (älter als 7 Tage oder mehr als 10 Minuten in der Zukunft), wird die Server-Zeit verwendet.
- **`oppref`** (Klick-Attribution) kommt vom Browser-Catcher im AJAX-`data` und steht auf Event-Ebene; die CAPI erfasst ihn nicht selbst. **`obref`** (Browser-Referenz des Pixels) steht ungehasht in `user`.
- **User-Daten** folgen der Datenminimierung: nur E-Mail-Hash, IP, User-Agent und `obref` — keine Namen, Telefonnummern, `external_id` oder Adressdaten.
- **`custom_event_name`** muss 1–64 Zeichen aus `[A-Za-z0-9_-]` haben, alphanumerisch beginnen und enden und darf keinem Standard-Event-Namen entsprechen; sonst wird das Signal-Event nicht gesendet (Browser und Server identisch).
- **`integration_source`** wird nicht gesetzt (laut Doku für Partner, die im Auftrag von Werbetreibenden senden).
- **Fehlerauswertung:** über `WP_SDTRK_Helper_Event::do_post()`, siehe [HTTP-Versand](README.md#http-versand-do_post).
- **UTM:** nicht im Payload (die Datenformen `contents`/`customer_action` kennen keine Zusatzfelder).
