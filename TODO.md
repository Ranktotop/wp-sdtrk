# TODO

Offene Aufgaben für `wp-sdtrk`. Bekannte Auffälligkeiten sind ausführlich in der Spec dokumentiert: [spec/99-findings.md](spec/99-findings.md).

## Offen

### 🔴 Meta-CAPI feuert serverseitig nicht (Dispatch-Bug)

**Gefunden bei:** Spec-Analyse v1.7.6.

**Problem:** Der Browser-Meta-Catcher sendet `type: 'meta'` ([public/js/wp-sdtrk-meta.js](public/js/wp-sdtrk-meta.js)). Der Dispatch in [public/class-wp-sdtrk-public-ajax.php](public/class-wp-sdtrk-public-ajax.php) bildet daraus `Wp_Sdtrk_Tracker_Meta`, die Klasse heißt aber `Wp_Sdtrk_Tracker_Fb` ([public/class-wp-sdtrk-tracker-meta.php](public/class-wp-sdtrk-tracker-meta.php)) — kein `class_alias`. Dadurch ist `class_exists()` false und `validateTracker` liefert für Meta `state => false`.

**Auswirkung:** Die Meta Conversions API (Server-Tracking) wird **nie ausgelöst**. GA4 und TikTok sind nicht betroffen; der Meta-Browser-Pixel funktioniert.

**Status:** offen — muss behoben werden.

**Lösungsoptionen:** Klasse in `Wp_Sdtrk_Tracker_Meta` umbenennen · ODER `class_alias('Wp_Sdtrk_Tracker_Fb', 'Wp_Sdtrk_Tracker_Meta')` ergänzen · ODER im Catcher `type: 'fb'` senden (Lade-/Namenslogik beachten).

**Nach dem Fix:** Eintrag aus [spec/99-findings.md](spec/99-findings.md) entfernen und [spec/02-server-tracking/platform-meta-capi.md](spec/02-server-tracking/platform-meta-capi.md) sowie die Feature-Matrix in [spec/00-overview.md](spec/00-overview.md) auf den neuen Ist-Zustand bringen (siehe [CLAUDE.md](CLAUDE.md)).

---

### 🟡 Alle externen API-Integrationen auf aktuellen Stand prüfen & ggf. aktualisieren

**Ziel:** Sämtliche Anbindungen an externe Tracking-Anbieter überprüfen, da sich die APIs seit der Implementierung geändert haben könnten. Wo nötig, auf den aktuellen Stand bringen (Endpoint-Versionen, Payload-Felder, Auth, Pflichtparameter).

**Betroffene Integrationen:**

- **Meta / Facebook Conversions API** — aktuell fest auf Graph-API `v11.0` ([public/class-wp-sdtrk-tracker-meta.php](public/class-wp-sdtrk-tracker-meta.php)). v11.0 ist veraltet → aktuelle Version prüfen.
- **TikTok Events API** — aktuell `open_api/v1.2` ([public/class-wp-sdtrk-tracker-tt.php](public/class-wp-sdtrk-tracker-tt.php)). Neuere Events-API-Version (z. B. Events API 2.0 / `/event/track/`) prüfen.
- **Google Analytics 4 Measurement Protocol** — Endpoint/Parameter gegen aktuelle Spec prüfen ([public/class-wp-sdtrk-tracker-ga.php](public/class-wp-sdtrk-tracker-ga.php)).
- **LinkedIn, Funnelytics, Mautic, Matomo** (browser-seitig) — Pixel-/Tag-Snippets und Aufruf-APIs auf Aktualität prüfen.

**Pro Integration zu prüfen:** Endpoint-URL & API-Version · Payload-/Feldstruktur · Authentifizierung (Token/Header) · neue Pflicht- oder empfohlene Parameter · Deprecation-Hinweise des Anbieters.

**Status:** offen — Prüfung erforderlich, Aktualisierung nur wo tatsächlich nötig.

**Nach Änderungen:** Die jeweiligen Plattform-Seiten unter [spec/02-server-tracking/](spec/02-server-tracking/) (und ggf. [spec/03-browser-tracking/](spec/03-browser-tracking/)) auf den neuen Ist-Zustand bringen; den Hinweis zu veralteten API-Versionen in [spec/99-findings.md](spec/99-findings.md) entsprechend anpassen (siehe [CLAUDE.md](CLAUDE.md)).

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
