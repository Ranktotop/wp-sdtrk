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
| TikTok | `Wp_Sdtrk_Tracker_Tt` | `business-api.tiktok.com/open_api/v1.2/pixel/track/` | ✅ |

> LinkedIn, Funnelytics, Mautic, Matomo besitzen **keinen** Server-Tracker — sie laufen ausschließlich browser-seitig ([03](../03-browser-tracking/README.md)).

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
