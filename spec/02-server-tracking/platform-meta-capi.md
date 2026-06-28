# 02 — Meta / Facebook Conversions API

- **Klasse:** `Wp_Sdtrk_Tracker_Fb`
- **Datei:** `public/class-wp-sdtrk-tracker-meta.php`
- **`type`-Kürzel (Browser):** `meta`
- **Status:** ⚠️ Server-seitig implementiert, feuert aber aktuell **nicht** — siehe [99 Befunde › Meta-CAPI-Dispatch](../99-findings.md#meta-capi-dispatch).

## Endpoint

```
POST https://graph.facebook.com/v11.0/{meta_pixelid}/events?access_token={meta_trk_server_token}
```

## Relevante Optionen

| Option | Bedeutung |
|--------|-----------|
| `meta_pixelid` | Pixel-ID |
| `meta_trk_server` | Server-Tracking aktiv |
| `meta_trk_server_token` | CAPI Access-Token |
| `meta_trk_debug` | Debug-Modus |
| `meta_trk_server_debug_code` | Test-Event-Code (Events-Manager) |

Vollständige Liste: [04 › Options-Referenz](../04-admin-and-options/option-reference.md#meta).

## Event-Namens-Mapping (kanonisch → Meta)

| kanonisch | Meta `event_name` |
|-----------|-------------------|
| (page) | `PageView` |
| `view_item` | `ViewContent` |
| `generate_lead` | `Lead` |
| `sign_up` | `CompleteRegistration` |
| `add_to_cart` | `AddToCart` |
| `begin_checkout` | `InitiateCheckout` |
| `purchase` | `Purchase` |

## Payload (Struktur)

```jsonc
{
  "data": [{
    "event_time": 1719424123,
    "event_id": "<dedup-id>",
    "event_name": "Purchase",
    "event_source_url": "<url>",
    "action_source": "website",
    "user_data": {
      "client_ip_address": "<ip>",
      "client_user_agent": "<ua>",
      "fbp": "<_fbp>",
      "fbc": "<_fbc>",
      "em": "<sha256(email)>",
      "fn": "<sha256(firstname)>",
      "ln": "<sha256(lastname)>"
    },
    "custom_data": {
      "currency": "EUR",
      "value": 49.0,
      "content_ids": ["<prodId>"],
      "content_type": "product",
      "content_name": "<prodName>",
      "utm_source": "…", "utm_medium": "…"
      // bei Click/Visibility zusätzlich buttonTag/itemTag
    },
    "contents": [{ "id": "<prodId>", "quantity": 1 }]
  }],
  "test_event_code": "<meta_trk_server_debug_code>"   // nur im Debug-Modus
}
```

## Identitäts-/Matching-Daten

- **Gehasht (SHA256):** `em` (E-Mail), `fn` (Vorname), `ln` (Nachname) — gebildet in `getData_user()`.
- **Ungehasht:** `client_ip_address`, `client_user_agent`, `fbp`, `fbc` (so von Meta erwartet).
- `fbp`/`fbc` kommen vom Browser-Catcher mit (`data.fbp` / `data.fbc`).

## Deduplizierung

Gemeinsame `event_id` für Browser-Pixel und CAPI. Für Signal-Events wird sie um ein Suffix ergänzt: `…-s{percent}` (Scroll), `…-t{time}` (Time), `…-b{tag}` (Click), `…-v{tag}` (Visibility). Details: [user-data-deduplication.md](user-data-deduplication.md).
