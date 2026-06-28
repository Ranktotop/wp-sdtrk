# TODO

Offene Aufgaben für `wp-sdtrk`. Bekannte Auffälligkeiten sind ausführlich in der Spec dokumentiert: [spec/99-findings.md](spec/99-findings.md).

## Offen

### 🟢 WooCommerce View-Item & Add-to-Cart einbauen

Zusätzlich zum bereits umgesetzten Purchase-Tracking sollen zwei weitere kanonische Events für WooCommerce gefeuert werden (Event-Modell kennt `view_item`/`add_to_cart` bereits):

- **View-Item:** **immer** auf einer Produkt-Einzelseite feuern, gespeist mit den Produktdaten (ID/SKU, Name, Preis/Wert, Währung). Browser-Pixel auf allen aktiven Plattformen; Server-API analog zum Purchase-Pfad (consent-gated, Dedup über gemeinsame `event_id`).
- **Add-to-Cart:** beim Hinzufügen eines Produkts in den Warenkorb feuern, mit den Produktdaten.

**Offene Designfragen:** Hooks (Produktseite via `is_product()`/`template_redirect`; ATC via `woocommerce_add_to_cart` bzw. AJAX-ATC), `event_id`-Strategie (keine Order-ID vorhanden → eigener stabiler Identifier je View/ATC), Reuse des bestehenden `wp-sdtrk-wc.js`-Browserpfads bzw. der Tracker. Bei Umsetzung Spec-Sektion [07 WooCommerce](spec/07-woocommerce/README.md) ergänzen.

**Status:** geplant — noch nicht umgesetzt.

---

### 🟡 Währung fest auf `EUR` — durch echte Währung ersetzen

Die Server-Tracker (Meta `getData_custom`/`fireTracking_Server_Event`, GA4, TikTok) sowie der Meta-Browser-Pfad (`get_data_custom`) setzen die Währung hart auf `"EUR"`. Für WooCommerce liefert der [Order-Mapper](public/class-wp-sdtrk-wc-order-mapper.php) bereits die Order-Währung, sie wird aber von den Trackern noch nicht genutzt → bei abweichender Shop-Währung falsche Meldung. **Tun:** Währung über das Event-Modell durchreichen und in den Payloads statt `EUR` verwenden. Betrifft nicht nur WooCommerce. Details: [spec/99-findings.md](spec/99-findings.md).

**Status:** geplant — noch nicht umgesetzt.

---

### 🟡 Funnelytics-Browser-Pixel live verifizieren

Der Code nutzt `cdn.funnelytics.io/track-v3.js` + `window.funnelytics.events.trigger()` ([public/js/wp-sdtrk-fl.js](public/js/wp-sdtrk-fl.js)). Die heute offiziell dokumentierte Basis ist `cdn.funnelytics.io/track.js` + `window.funnelytics.init()`. `track-v3.js` kann die neuere Variante sein — daher **nicht blind ändern**, sondern in einer Live-Umgebung prüfen, ob Events ankommen. Nur bei nachgewiesener Abweichung anpassen. Details: [tasks/api-audit.md](tasks/api-audit.md) (Abschnitt 4).

> Die übrigen API-Integrationen wurden geprüft und auf den aktuellen Stand gebracht (Meta CAPI `v23.0`, TikTok Events API 2.0 `v1.3`, GA4 MP verifiziert, übrige Browser-Pixel verifiziert). Der Meta-CAPI-Dispatch-Bug ist behoben. Siehe [tasks/plan.md](tasks/plan.md) und [tasks/api-audit.md](tasks/api-audit.md).

## Umgesetzt (Live-Verifikation offen)

### ✅ WooCommerce-Integration (Purchase)

Umgesetzt: Auto-Erkennung + Redux-Switch, Order→Event-Mapping, Browser-Purchase auf der Order-Received-Seite (alle Plattformen), Server-APIs auf Order-Status (consent-gated, dedupliziert), Spec-Sektion [07 WooCommerce](spec/07-woocommerce/README.md). Siehe [tasks/plan.md](tasks/plan.md) / [tasks/wc-design.md](tasks/wc-design.md).

**Offen:** Live-Verifikation auf echter HTTPS-Seite (Browser-Pixel feuern lokal nicht zuverlässig).

---

### ✅ Produkt-Feed (WooCommerce)

Umgesetzt: RSS-2.0/`g:`-Feed-Generator, token-geschützter Endpoint, täglicher Cron (reaktiviert), Spec [07 › Produkt-Feed](spec/07-woocommerce/product-feed.md). Siehe [tasks/feed-design.md](tasks/feed-design.md).

**Offen:** Live-Verifikation (Feed-URL/Token, Merchant-Center-/Commerce-Manager-Import, Cron-Lauf).

> Der vollständige Ist-Zustand der WooCommerce-Integration und des Feeds ist in [spec/07-woocommerce/](spec/07-woocommerce/README.md) dokumentiert.
