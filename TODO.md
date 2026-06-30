# TODO

Offene Aufgaben für `wp-sdtrk`. Bekannte Auffälligkeiten sind ausführlich in der Spec dokumentiert: [spec/99-findings.md](spec/99-findings.md).

## Offen

### 🟡 Währung fest auf `EUR` — durch echte Währung ersetzen

Die Server-Tracker (Meta `getData_custom`/`fireTracking_Server_Event`, GA4, TikTok) sowie der Meta-Browser-Pfad (`get_data_custom`) setzen die Währung hart auf `"EUR"`. Für WooCommerce liefert der [Order-Mapper](public/class-wp-sdtrk-wc-order-mapper.php) bereits die Order-Währung, sie wird aber von den Trackern noch nicht genutzt → bei abweichender Shop-Währung falsche Meldung. **Tun:** Währung über das Event-Modell durchreichen und in den Payloads statt `EUR` verwenden. Betrifft nicht nur WooCommerce. Details: [spec/99-findings.md](spec/99-findings.md).

**Status:** geplant — noch nicht umgesetzt.

---

### 🟡 Funnelytics-Browser-Pixel live verifizieren

Der Code nutzt `cdn.funnelytics.io/track-v3.js` + `window.funnelytics.events.trigger()` ([public/js/wp-sdtrk-fl.js](public/js/wp-sdtrk-fl.js)). Die heute offiziell dokumentierte Basis ist `cdn.funnelytics.io/track.js` + `window.funnelytics.init()`. `track-v3.js` kann die neuere Variante sein — daher **nicht blind ändern**, sondern in einer Live-Umgebung prüfen, ob Events ankommen. Nur bei nachgewiesener Abweichung anpassen.

> Die übrigen API-Integrationen wurden geprüft und auf den aktuellen Stand gebracht (Meta CAPI `v23.0`, TikTok Events API 2.0 `v1.3`, GA4 MP verifiziert, übrige Browser-Pixel verifiziert). Der Meta-CAPI-Dispatch-Bug ist behoben.

## Umgesetzt (Live-Verifikation offen)

### ✅ WooCommerce-Integration (Purchase)

Umgesetzt: Auto-Erkennung + Redux-Switch, Order→Event-Mapping, Browser-Purchase auf der Order-Received-Seite (alle Plattformen), Server-APIs auf Order-Status (consent-gated, dedupliziert), Spec-Sektion [07 WooCommerce](spec/07-woocommerce/README.md).

**Offen:** Live-Verifikation auf echter HTTPS-Seite (Browser-Pixel feuern lokal nicht zuverlässig).

---

### ✅ WooCommerce Funnel-Events (ViewItem / AddToCart / InitiateCheckout)

Umgesetzt: `view_item` (Produkt-Einzelseite), `add_to_cart` (Session-gepuffert, beim nächsten Seitenaufbau geseedet) und `begin_checkout` (Checkout-Seite) über dasselbe Engine-Seed-Modell wie Purchase — Browser **und** Server in einem Durchlauf, Präzedenz `order > beginCheckout > addToCart > viewItem`. Spec [07 › ViewItem/AddToCart](spec/07-woocommerce/view-item-and-add-to-cart.md) + [07 › InitiateCheckout](spec/07-woocommerce/initiate-checkout.md).

**Offen:** Live-Verifikation auf echter HTTPS-Seite (Browser + Server feuern auf Produkt-/Checkout-Seiten).

---

### ✅ Produkt-Feed (WooCommerce)

Umgesetzt: RSS-2.0/`g:`-Feed-Generator, token-geschützter Endpoint, täglicher Cron (reaktiviert), Verwaltungsseite für Produkt-Ein-/Ausschluss, Spec [07 › Produkt-Feed](spec/07-woocommerce/product-feed.md) + [07 › Produktfeed-Verwaltung](spec/07-woocommerce/feed-management.md).

**Offen:** Live-Verifikation (Feed-URL/Token, Merchant-Center-/Commerce-Manager-Import, Cron-Lauf).

> Der vollständige Ist-Zustand der WooCommerce-Integration und des Feeds ist in [spec/07-woocommerce/](spec/07-woocommerce/README.md) dokumentiert.
