# 07 — Browser-Purchase (Order-Received-Seite)

## Einbindung

`Wp_Sdtrk_WC_Integration::enqueue_purchase_assets()` (Hook `wp_enqueue_scripts`) bindet [public/js/wp-sdtrk-wc.js](../../public/js/wp-sdtrk-wc.js) **nur** ein, wenn:

1. `is_active()` (WooCommerce + Schalter), **und**
2. `is_order_received_page()` true ist und sich über `get_query_var('order-received')` eine Order auflösen lässt.

Das Skript hängt als Dependency an `wp_sdtrk-engine` und erhält per `wp_localize_script` das Objekt `wp_sdtrk_wc` mit den Order-Daten (`order`): `orderId`, `key`, `value`, `currency`, `prodId`, `prodName`, `email`, `firstName`, `lastName`, `contents` (Positionsliste), `source`, `ip`, `agent`, `utm`.

## Feuern

`wp-sdtrk-wc.js` wartet auf die laufende Engine (`window.wp_sdtrk_engine_class`) und:

1. **baut ein Purchase-Event** (`Wp_Sdtrk_Event`) aus `wp_sdtrk_wc.order` (`eventName = 'purchase'`, Wert, Order-ID als `eventId`, Produkt, Käuferdaten, Quelle/IP/Agent);
2. **feuert browser-only** (`target 0`): Es nutzt die von der Engine bereits konstruierten Catcher (Pixel sind einmalig geladen) und tauscht für den Aufruf von `catchEventHit(0)` temporär das Catcher-Event gegen das Purchase-Event. Dadurch wird kein Pixel re-initialisiert (z. B. kein doppelter GA-`page_view`) und das Page-Event der Engine bleibt unangetastet.

Jeder aktive Catcher feuert sein Purchase/Conversion-Event: Meta (`trackSingle Purchase`), TikTok (`ttq.track`), GA4, Funnelytics, Mautic, Matomo (`_paq trackEvent`), LinkedIn (Conversion-Mapping). Die `eventID`/`event_id` basiert auf der Order-ID (Dedup mit dem Server-Event).

Der **Server-Pfad** wird hier bewusst **nicht** ausgelöst (kein `target 1`); er läuft über den Order-Status-Hook ([server-purchase.md](server-purchase.md)).

## Consent

Browser-Consent wird wie sonst pro Catcher beim Engine-Aufbau ausgewertet (`helper.has_consent`, Borlabs v2/v3). Nur Catcher mit erteiltem Browser-Consent (`b_enabled`) feuern. Wird Consent erst nach dem Seitenaufbau erteilt, greift der bestehende Backload-Mechanismus der Catcher **nicht** für das WC-Purchase-Event (bekannte Einschränkung).

## Reload

Mehrfaches Laden der Order-Received-Seite ist unkritisch: Die Plattformen deduplizieren über die gemeinsame `eventID` (= Order-ID).
