# TODO

Offene Aufgaben für `wp-sdtrk`. Bekannte Auffälligkeiten sind ausführlich in der Spec dokumentiert: [spec/99-findings.md](spec/99-findings.md).

## Offen

### 🟡 Funnelytics-Browser-Pixel live verifizieren

Der Code nutzt `cdn.funnelytics.io/track-v3.js` + `window.funnelytics.events.trigger()` ([public/js/wp-sdtrk-fl.js](public/js/wp-sdtrk-fl.js)). Die heute offiziell dokumentierte Basis ist `cdn.funnelytics.io/track.js` + `window.funnelytics.init()`. `track-v3.js` kann die neuere Variante sein — daher **nicht blind ändern**, sondern in einer Live-Umgebung prüfen, ob Events ankommen. Nur bei nachgewiesener Abweichung anpassen. Details: [tasks/api-audit.md](tasks/api-audit.md) (Abschnitt 4).

> Die übrigen API-Integrationen wurden geprüft und auf den aktuellen Stand gebracht (Meta CAPI `v23.0`, TikTok Events API 2.0 `v1.3`, GA4 MP verifiziert, übrige Browser-Pixel verifiziert). Der Meta-CAPI-Dispatch-Bug ist behoben. Siehe [tasks/plan.md](tasks/plan.md) und [tasks/api-audit.md](tasks/api-audit.md).

## Geplante Features

### 🟢 WooCommerce-Integration

**Ziel:** Das Plugin (bisher bewusst für Nicht-WooCommerce-Seiten gebaut) optional WooCommerce-kompatibel machen.

**Vorstellung:**

- **Auto-Erkennung:** System erkennt automatisch, ob WooCommerce installiert/aktiv ist. Nur dann wird die WooCommerce-Schnittstelle in den Einstellungen sichtbar.
- **Aktivierung per Schalter:** Die Integration lässt sich über einen Schieberegler (Redux-Switch) aktivieren/deaktivieren.
- **Verhalten bei aktiver Integration & eingehender Bestellung:**
  - Auf der **Bestellbestätigungsseite** (Order-Received / Thank-You) wird der **Browser-Pixel** ausgelöst und die Conversion getrackt — inkl. Produkte, Werte, Währung, Bestelldaten.
  - Die **Server-APIs** (CAPI / MP / Events API) werden ebenfalls gefeuert, sofern sie konfiguriert/aktiviert sind **und** das Tracking-Cookie (Consent) akzeptiert wurde.
- Wiederverwendung der bestehenden Event-/Tracker-Architektur (`Wp_Sdtrk_Tracker_Event` + Catcher/Tracker), Daten aus dem WooCommerce-Order-Objekt befüllen (Produkte → content_ids/items, Summe → value, etc.). Deduplizierung (gemeinsame `event_id` Browser↔Server) beachten.

**Offene Designfragen (vor Umsetzung zu klären):** genaue Hooks (z. B. `woocommerce_thankyou`), Mapping WooCommerce-Daten → kanonisches Event-Modell, Zusammenspiel mit Page-Level-Metabox/Consent-Bypass, Verhalten bei mehreren Zahlungsmethoden/asynchronen Bestätigungen.

**Status:** geplant — Konzept steht, noch nicht umgesetzt.

**Bei Umsetzung:** Neue Spec-Sektion anlegen (z. B. `spec/07-woocommerce/`) mit Index und den üblichen Querverweisen; Feature-Matrix in [spec/00-overview.md](spec/00-overview.md) ergänzen (siehe [CLAUDE.md](CLAUDE.md)).

---

### 🟢 Produkt-Feed (nur mit aktiver WooCommerce-Integration)

**Ziel:** Aus den aktiven WooCommerce-Produkten automatisch einen Produkt-Feed generieren, der bei Meta bzw. Google hinterlegt werden kann, sodass dort stets die aktuellen Produkte vorliegen.

**Vorstellung:**

- Nur verfügbar/aktiv, wenn die WooCommerce-Integration aktiviert ist (siehe oben).
- Generierung eines Feeds (z. B. CSV; ggf. zusätzlich XML/Google-/Meta-Format) aus den **aktiven** Produkten.
- Feed über eine abrufbare URL bereitstellen, die in Meta Commerce Manager / Google Merchant Center hinterlegt werden kann.
- Feed regelmäßig aktualisieren (Cron — die vorhandene, aktuell leere `WP_SDTRK_Cron`-Infrastruktur ließe sich hierfür nutzen).

**Offene Designfragen (vor Umsetzung zu klären):** Feld-Mapping (Produkt → Feed-Spalten je Plattform), Feed-Format(e), Speicherort/URL & Zugriffsschutz, Aktualisierungsintervall, Umgang mit Varianten/Beständen/Preisen.

**Status:** geplant — Konzept steht, noch nicht umgesetzt.

**Bei Umsetzung:** In der WooCommerce-Spec-Sektion dokumentieren; falls Cron reaktiviert wird, [spec/01-architecture/lifecycle.md](spec/01-architecture/lifecycle.md) und den Cron-Befund in [spec/99-findings.md](spec/99-findings.md) auf den neuen Ist-Zustand bringen (siehe [CLAUDE.md](CLAUDE.md)).
