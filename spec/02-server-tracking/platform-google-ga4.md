# 02 — Google Analytics 4 (Measurement Protocol)

- **Klasse:** `Wp_Sdtrk_Tracker_Ga`
- **Datei:** `public/class-wp-sdtrk-tracker-ga.php`
- **`type`-Kürzel (Browser):** `ga`
- **Status:** ✅ funktionsfähig (Dispatch trifft die Klasse korrekt).

## Endpoint

```
POST https://www.google-analytics.com/mp/collect?api_secret={ga_trk_server_token}&measurement_id={ga_measurement_id}
```

Im Debug-Modus (ohne „live"):

```
POST https://www.google-analytics.com/debug/mp/collect?…
```

Auswahllogik:
```php
$baseUrl = ($this->debugMode && ! $this->debugModeLive)
    ? "https://www.google-analytics.com/debug/mp/collect"
    : "https://www.google-analytics.com/mp/collect";
```

## Relevante Optionen

| Option | Bedeutung |
|--------|-----------|
| `ga_measurement_id` | Measurement-ID (`G-XXXX…`) |
| `ga_trk_server` | Server-Tracking aktiv |
| `ga_trk_server_token` | API Secret (Datastream) |
| `ga_trk_debug` | Debug (nutzt `/debug/`-Endpoint) |
| `ga_trk_debug_live` | Debug, aber gegen Live-Endpoint |

## Event-Namens-Mapping (kanonisch → GA4)

| kanonisch | GA4 `name` |
|-----------|-----------|
| (page) | `page_view` |
| `view_item` | `view_item` |
| `generate_lead` | `generate_lead` |
| `sign_up` | `sign_up` |
| `add_to_cart` | `add_to_cart` |
| `begin_checkout` | `begin_checkout` |
| `purchase` | `purchase` |

## Payload (Struktur)

```jsonc
{
  "client_id": "<cid vom Browser>",
  "events": [{
    "name": "purchase",
    "params": {
      "page_location": "<url>",
      "page_path": "<path>",
      "page_title": "<title>",
      "page_referrer": "<referer>",
      "transaction_id": "<dedup-id>",
      "value": 49.0,
      "currency": "EUR",
      "items": [{ "id": "<prodId>", "name": "<prodName>", "price": 49.0, "quantity": 1, "brand": "<brand>" }],
      "debug_mode": true,            // wenn Debug aktiv
      "non_interaction": true,
      "plugin": "Wp-Sdtrk",
      "utm_source": "…", "utm_medium": "…"
      // Click/Visibility: zusätzlich buttonTag / itemTag
    }
  }]
}
```

## Besonderheiten

- **`client_id` (`cid`)** kommt vom Browser (GA4-Client-ID). Ohne `cid` kein verlässliches GA4-Matching.
- **Kein Hashing**: GA4 MP nimmt Klartextwerte; E-Mail/Name werden hier nicht (gehasht) übertragen.
- **UTM-Parameter** werden direkt in `params` eingebettet.
- **Deduplizierung** über `transaction_id` (= `event_id` aus dem Event), kombiniert mit `name` + `client_id`.
