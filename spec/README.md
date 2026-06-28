# Spezifikation — Smart Server Side Tracking Plugin (`wp-sdtrk`)

Diese Spec dokumentiert den **Ist-Zustand** des WordPress-Plugins *Smart Server Side Tracking Plugin* in Version **1.7.6**. Sie wurde durch Analyse des Quellcodes erstellt und dient als technische Referenz und Onboarding-Dokument.

> **Zweck des Plugins (Kurzfassung):** Conversion-Tracking für WordPress-Seiten — sowohl **browser-basiert** (Pixel/Tags) als auch **server-to-server** über die jeweiligen Conversion-APIs. Kern-Zielgruppe sind Seiten ohne eigenen Shop; bei aktivem WooCommerce ist zusätzlich eine optionale **WooCommerce-Integration** verfügbar ([07](07-woocommerce/README.md)). Ziel ist verlässliches, (DSGVO-orientiertes) Tracking trotz Adblockern, ITP/iOS-14-Restriktionen und Cookie-Consent.

---

## Navigation

| # | Bereich | Inhalt |
|---|---------|--------|
| 00 | [Überblick](00-overview.md) | Metadaten, Zielgruppe, Feature-Matrix, Glossar |
| 01 | [Architektur](01-architecture/README.md) | Bootstrap, Loader-Pattern, Verzeichnis-/Namenskonventionen, Lebenszyklus |
| 02 | [Server-Tracking (Conversion API / S2S)](02-server-tracking/README.md) | AJAX-Pipeline, Event-Modell, Meta CAPI, GA4 MP, TikTok Events API, User-Daten & Deduplizierung |
| 03 | [Browser-Tracking (JavaScript)](03-browser-tracking/README.md) | Engine, Event-Erfassung, Catcher-Module, Consent, Cookies/Fingerprint/Decryption |
| 04 | [Admin & Optionen](04-admin-and-options/README.md) | Redux-Settings, Menüstruktur, Options-Referenz, Metabox, Helper |
| 05 | [Datenmodell](05-data-model/README.md) | Base-Model/ORM, DB-Schema, LinkedIn-Mapping |
| 06 | [Integrationen](06-integrations.md) | Digistore24-Decryption, Update-Checker, externe Dependencies |
| 07 | [WooCommerce](07-woocommerce/README.md) | Optionale WooCommerce-Integration: Aktivierung, Order-Mapping, Browser-/Server-Purchase, Dedup |
| 99 | [Befunde & offene Punkte](99-findings.md) | Auffälligkeiten, mutmaßliche Bugs, ungenutzte Bausteine |

---

## Schnelleinstieg nach Frage

- **„Wie startet das Plugin?"** → [01 Architektur › Bootstrap & Loader](01-architecture/bootstrap-and-loader.md)
- **„Wie kommt ein Conversion-Event vom Browser zum Facebook/Google/TikTok-Server?"** → [02 › AJAX-Pipeline](02-server-tracking/ajax-pipeline.md)
- **„Welche Tracking-Plattformen werden unterstützt?"** → [00 Überblick › Feature-Matrix](00-overview.md#feature-matrix)
- **„Welche Einstellungen gibt es?"** → [04 › Options-Referenz](04-admin-and-options/option-reference.md)
- **„Was ist das LinkedIn-Mapping?"** → [05 › LinkedIn-Mapping](05-data-model/linkedin-mapping.md)
- **„Was funktioniert evtl. nicht?"** → [99 Befunde](99-findings.md)

---

## Methodik & Geltungsbereich

- **Basis:** Statische Quellcode-Analyse des Plugin-Stands v1.7.6 (Branch `main`).
- **Verifiziert:** Architektur, Klassenstruktur, Hook-Registrierung, AJAX-Dispatch, API-Endpoints, DB-Schema wurden direkt im Code geprüft.
- **Nicht Bestandteil:** Laufzeit-/Integrationstests, externe API-Verträge (Meta/Google/TikTok) jenseits der im Code verwendeten Endpoints, `vendor/`-Code (Redux, Update-Checker).
- **Stand:** Siehe Git-HEAD zum Zeitpunkt der Erstellung. Bei Code-Änderungen ist diese Spec entsprechend nachzuführen.
