# 02 — TikTok Events API

- **Klasse:** `Wp_Sdtrk_Tracker_Tt`
- **Datei:** `public/class-wp-sdtrk-tracker-tt.php`
- **`type`-Kürzel (Browser):** `tt`
- **Status:** ✅ funktionsfähig.

## Endpoint

```
POST https://business-api.tiktok.com/open_api/v1.2/pixel/track/
Header: Access-Token: {tt_trk_server_token}
        Content-Type: application/json
```

## Relevante Optionen

| Option | Bedeutung |
|--------|-----------|
| `tt_pixelid` | Pixel-Code |
| `tt_trk_server` | Server-Tracking aktiv |
| `tt_trk_server_token` | Access-Token |
| `tt_trk_debug` | Debug-Modus |
| `tt_trk_server_debug_code` | Test-Event-Code |

## Event-Namens-Mapping (kanonisch → TikTok)

TikTok kennt kein `PageView`; der Page-Handler sendet `ViewContent`.

| kanonisch / Handler | TikTok `event` |
|---------------------|----------------|
| (page) | `ViewContent` |
| `view_item` | `ViewContent` |
| `generate_lead` | `SubmitForm` |
| `sign_up` | `CompleteRegistration` |
| `add_to_cart` | `AddToCart` |
| `begin_checkout` | `InitiateCheckout` |
| `purchase` | `PlaceAnOrder` |

## Payload (Struktur)

```jsonc
{
  "pixel_code": "<tt_pixelid>",
  "event_id": "<dedup-id>_<hash>",
  "timestamp": "2024-01-15T10:30:00Z",   // ISO 8601 (nicht Unix!)
  "event": "PlaceAnOrder",
  "test_event_code": "<tt_trk_server_debug_code>",   // optional
  "context": {
    "page": { "url": "<url>", "referrer": "<referer>" },
    "ip": "<ip>",
    "user_agent": "<ua>",
    "ad":   { "callback": "<ttc / ttclid>" },
    "user": { "email": "<sha256(email)>", "external_id": "<ttc>" }
  },
  "properties": {
    "contents": [{ "content_id": "<prodId>", "content_name": "<prodName>", "content_type": "product", "quantity": 1, "price": 49.0 }],
    "currency": "EUR",
    "value": 49.0
  }
}
```

## Besonderheiten

- **Timestamp** im ISO-8601-Format (Abweichung zu Meta = Unix).
- **`event_id`**: Basis-ID + `_<hash>` (Hash kommt vom Browser) zur Deduplizierung.
- **Gehasht (SHA256):** `context.user.email`.
- **Click-ID:** `ttc` (aus `ttclid`) landet in `context.ad.callback` und `context.user.external_id`.
- **UTM:** laut Code-Kommentar von der TikTok-API **nicht unterstützt** → nicht im Payload.
