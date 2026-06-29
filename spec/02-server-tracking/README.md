# 02 — Server-Tracking (Conversion API / Server-to-Server)

Dies ist der Kern des Plugins: Conversion-Events werden **server-seitig** per cURL direkt an die APIs der Plattformen gesendet, unabhängig vom Browser-Pixel. Dadurch bleiben Conversions auch bei Adblockern, ITP/iOS-14 und Pixel-Blockaden erhalten.

## Inhalt

| Datei | Thema |
|-------|-------|
| [ajax-pipeline.md](ajax-pipeline.md) | Vom Browser-AJAX zum Server-Tracker: Nonce, Dispatch, `validateTracker()` |
| [event-model.md](event-model.md) | `Wp_Sdtrk_Tracker_Event` und das Event-Datenmodell |
| [platform-meta-capi.md](platform-meta-capi.md) | Meta/Facebook Conversions API (`Wp_Sdtrk_Tracker_Meta`) |
| [platform-google-ga4.md](platform-google-ga4.md) | Google Analytics 4 Measurement Protocol (`Wp_Sdtrk_Tracker_Ga`) |
| [platform-tiktok.md](platform-tiktok.md) | TikTok Events API (`Wp_Sdtrk_Tracker_Tt`) |
| [user-data-deduplication.md](user-data-deduplication.md) | User-Daten-Erfassung, SHA256-Hashing, Event-Deduplizierung |

## Unterstützte Plattformen (server-seitig)

| Plattform | Klasse | Endpoint | Status |
|-----------|--------|----------|--------|
| Meta CAPI | `Wp_Sdtrk_Tracker_Meta` | `graph.facebook.com/v23.0/{pixel}/events` | ✅ |
| GA4 MP | `Wp_Sdtrk_Tracker_Ga` | `www.google-analytics.com/mp/collect` | ✅ |
| TikTok | `Wp_Sdtrk_Tracker_Tt` | `business-api.tiktok.com/open_api/v1.3/event/track/` | ✅ |

> LinkedIn, Funnelytics, Mautic, Matomo besitzen **keinen** Server-Tracker — sie laufen ausschließlich browser-seitig ([03](../03-browser-tracking/README.md)).

## Maßgebliche Anbieter-Dokumentation (immer beachten)

> ⚠️ **Verbindlich:** Die Payload-Struktur jeder Plattform (welches Feld in welchem Objekt liegt, Pflichtfelder, erlaubte Werte, Endpoint-Version) wird **ausschließlich** von der offiziellen Anbieter-Doku bestimmt. Vor **jeder** Änderung an einem Server- oder Browser-Payload sind die hier verlinkten Quellen zu prüfen — nicht aus dem Gedächtnis oder aus Sekundärquellen arbeiten. Diese Doku ändert sich (Versionen, Feldnamen, Objekt-Verschachtelung); Abweichungen führen zu still verworfenen oder falsch zugeordneten Events.

**Feld-Platzierung (verifizierter Ist-Stand der Anbieter-Verträge):**

| Plattform | Produkt-/Wert-Felder gehören in … | Referenz |
|-----------|-----------------------------------|----------|
| Meta CAPI | `custom_data`: `content_ids`, `contents` `[{id,quantity}]`, `content_type`, `currency`, `value` | [custom_data-Parameter](https://developers.facebook.com/docs/marketing-api/conversions-api/parameters/custom-data/) · [Parameter-Übersicht](https://developers.facebook.com/docs/marketing-api/conversions-api/parameters/) · [CAPI](https://developers.facebook.com/docs/marketing-api/conversions-api/) |
| Meta Pixel (Browser) | `fbq('track', …, custom_data)`; Advanced Matching (`em`/`fn`/`ln`) bei `fbq('init', pixelId, user_data)` | [Meta-Pixel-Referenz](https://developers.facebook.com/docs/meta-pixel/reference) · [Advanced Matching](https://developers.facebook.com/docs/meta-pixel/implementation/conversion-tracking#advanced-matching) |
| GA4 MP | Event-`params`: `items[]` `{item_id,item_name,price,quantity}`, `currency`, `value`, `transaction_id` | [MP-Events-Referenz](https://developers.google.com/analytics/devguides/collection/protocol/ga4/reference/events) · [E-Commerce-Events](https://developers.google.com/analytics/devguides/collection/ga4/ecommerce) |
| TikTok Events API 2.0 | `properties`: `contents[]` `{content_id,content_name,content_type,quantity,price}`, `currency`, `value`; Identifier in `user` | [About Events API](https://ads.tiktok.com/help/article/events-api) · [Parameter](https://ads.tiktok.com/help/article/about-parameters) · Endpoint `v1.3/event/track/` |
| TikTok Pixel (Browser) | `ttq.track(event, properties)` mit `contents[]`/`currency`/`value` | [About Events API](https://ads.tiktok.com/help/article/events-api) |

Die nativen Browser-APIs **aller** Plattformen — inkl. der reinen Browser-Plattformen LinkedIn, Funnelytics, Mautic, Matomo (kein Server-Tracker) — sind mit offiziellen Doku-Links unter [03 › Catcher › Maßgebliche Anbieter-Dokumentation](../03-browser-tracking/catchers.md#2a-maßgebliche-anbieter-dokumentation-immer-beachten) erfasst.

## Datenfluss (Kurzform)

```
Browser-Catcher.sendData(handler, data)
   └─ AJAX POST admin-ajax.php
        action = wp_sdtrk_handle_public_ajax_callback
        func   = 'validateTracker'
        data   = { event:{…}, type:'meta|ga|tt', handler:'Page|Event|…', data:{fbp,fbc,cid,ttc,…} }
        _nonce = security_wp-sdtrk
   ↓ (PHP)
Wp_Sdtrk_Public_Ajax_Handler::handle_public_ajax_callback()
   ├─ wp_verify_nonce(...)            # Pflicht
   ├─ method_exists($this, func)?     # nur erlaubte Methoden
   └─ validateTracker($data, $meta)
        ├─ new Wp_Sdtrk_Tracker_Event($data['event'])
        ├─ $class = 'Wp_Sdtrk_Tracker_' . ucfirst($data['type'])
        └─ if class_exists: $tracker->fireTracking_Server($event, $handler, $data['data'])
              └─ cURL → Conversion-API der Plattform
```

Die gemeinsame Logik (Hashing, IP/User-Agent, `event_id`, cURL) lebt teilweise in `WP_SDTRK_Helper_Event` und teilweise pro Tracker. Details in den Unterseiten.
