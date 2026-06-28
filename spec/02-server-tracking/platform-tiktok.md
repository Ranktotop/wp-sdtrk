# 02 — TikTok Events API

- **Klasse:** `Wp_Sdtrk_Tracker_Tt`
- **Datei:** `public/class-wp-sdtrk-tracker-tt.php`
- **`type`-Kürzel (Browser):** `tt`
- **Status:** ✅ funktionsfähig.

## Endpoint

```
POST https://business-api.tiktok.com/open_api/v1.3/event/track/
Header: Access-Token: {tt_trk_server_token}
        Content-Type: application/json
```

Events API 2.0 (`v1.3/event/track`). Ein Request transportiert genau ein Event im `data`-Array.

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
  "event_source": "web",
  "event_source_id": "<tt_pixelid>",
  "test_event_code": "<tt_trk_server_debug_code>",   // optional, top-level
  "data": [{
    "event": "PlaceAnOrder",
    "event_id": "<dedup-id>_<hash>",
    "event_time": 1719424123,            // Unix-Sekunden (Integer)
    "page": { "url": "<url>", "referrer": "<referer>" },
    "user": {
      "ip": "<ip>",
      "user_agent": "<ua>",
      "email": "<sha256(email)>",
      "ttclid": "<ttc / ttclid>",
      "ttp": "<_ttp>"
    },
    "properties": {
      "contents": [{ "content_id": "<prodId>", "content_name": "<prodName>", "content_type": "product", "quantity": 1, "price": 49.0 }],
      "currency": "EUR",
      "value": 49.0
    }
  }]
}
```

## Besonderheiten

- **Envelope:** `event_source: "web"` + `event_source_id` (Pixel-Code); das eigentliche Event steckt im `data`-Array.
- **`event_time`** als Unix-Sekunden (Integer) — wie bei Meta.
- **`event_id`**: Basis-ID + `_<hash>` (Hash kommt vom Browser) zur Deduplizierung.
- **`user`** bündelt Identitäts-/Kontextdaten: `ip`, `user_agent`, gehashte `email` (SHA256), `ttclid` (aus `ttclid`-Param) und `ttp` (aus `_ttp`-Cookie).
- **UTM:** laut Code-Kommentar von der TikTok-API **nicht unterstützt** → nicht im Payload.
