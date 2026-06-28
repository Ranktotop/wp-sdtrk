# 00 — Überblick

## Plugin-Metadaten

| Eigenschaft | Wert |
|-------------|------|
| **Name** | Smart Server Side Tracking Plugin |
| **Slug / Textdomain** | `wp-sdtrk` |
| **Version** | 1.7.6 (Konstante `WP_SDTRK_VERSION`) |
| **Autor** | Marc Meese |
| **Author URI / Plugin URI** | https://marcmeese.de/ |
| **Lizenz** | GPL-2.0+ |
| **Requires WordPress** | 6.0.0+ (laut README), getestet bis 6.8.x |
| **Composer-Paket** | `rankt/wp_sdtrk` |
| **PSR-4-Namespace (deklariert)** | `Rankt\WpSmartServerSideTracking\` → `src/` *(siehe [99 Befunde](99-findings.md), `src/` existiert nicht)* |
| **Update-Quelle** | GitHub `Ranktotop/wp-sdtrk` via Plugin-Update-Checker (Release-Assets) |

## Zweck & Zielgruppe

Das Plugin ergänzt klassisches Browser-Pixel-Tracking um **serverseitiges Conversion-Tracking** (Conversion API / Measurement Protocol) für WordPress-Seiten, die **kein WooCommerce** einsetzen — typischerweise Landingpages, Funnels und Verkaufsseiten, die externe Zahlungsanbieter (z. B. Digistore24/CopeCart) nutzen.

Motivation laut README: Seit iOS-14/ITP und durch Adblocker geht browser-seitig ein erheblicher Teil der Conversion-Signale verloren. Server-to-Server-Tracking schließt diese Lücke.

**Typische Nutzer:** Online-Marketer / Seitenbetreiber, die Meta-, Google-, TikTok- und LinkedIn-Kampagnen mit verlässlichen Conversion-Daten versorgen wollen.

## Feature-Matrix

Welche Plattform wird **browser-seitig** (Pixel/Tag), welche zusätzlich **server-seitig** (Conversion-API) bedient:

| Plattform | Kürzel (`type`) | Browser-Pixel | Server-API (S2S) | PHP-Tracker-Klasse |
|-----------|------|:---:|:---:|--------------------|
| Meta / Facebook | `meta` | ✅ | ⚠️ vorhanden, feuert aber nicht* | `Wp_Sdtrk_Tracker_Fb` |
| Google Analytics 4 | `ga` | ✅ | ✅ | `Wp_Sdtrk_Tracker_Ga` |
| TikTok | `tt` | ✅ | ✅ | `Wp_Sdtrk_Tracker_Tt` |
| LinkedIn | `lin` | ✅ | ❌ | – (nur Browser) |
| Funnelytics | `fl` | ✅ | ❌ | – |
| Mautic | `mtc` | ✅ | ❌ | – |
| Matomo | `mtm` | ✅ | ❌ | – |

> \* Meta-CAPI ist serverseitig implementiert (`Wp_Sdtrk_Tracker_Fb`), wird aber wegen einer Klassennamen-/Dispatch-Diskrepanz aktuell nicht ausgelöst. Details: [99 Befunde](99-findings.md#meta-capi-dispatch).

**Weitere Funktionsbereiche:**

- **Signal-Events** (zusätzlich zu Standard-Conversions): Zeit auf Seite, Scroll-Tiefe, Button-Klicks, Element-Sichtbarkeit.
- **Cookieloses Fingerprinting** als Fallback-Identifier.
- **Cookie-Consent-Integration** für Borlabs Cookie (v2 **und** v3).
- **Digistore24-Datenentschlüsselung** (verschlüsselte Thank-You-Page-Parameter).
- **LinkedIn Conversion-Mapping** mit regelbasierter Zuordnung (eigene DB-Tabelle).
- **Page-Level-Überschreibungen** via Redux-Metabox (Produkt-ID, Consent-Bypass).

## Tech-Stack

| Schicht | Technologie |
|---------|-------------|
| Backend | PHP (prozedural-OOP, WordPress Plugin Boilerplate-Stil), kein Framework |
| Admin-UI | Redux Framework (`vendor/redux`) |
| Frontend | Vanilla JS + jQuery, klassenbasierte Module (ES5/ES6-Mix) |
| Persistenz | `wp_options` (Redux), `wp_postmeta` (Metabox), 1 eigene Tabelle (`{prefix}_sdtrk_linkedin`) |
| HTTP zu APIs | cURL (server-seitig) |
| Updates | `yahnis-elsts/plugin-update-checker` v5 (GitHub Releases) |
| Build/Deps | Composer (`composer/installers`, Redux, Update-Checker) |

## Glossar

| Begriff | Bedeutung |
|---------|-----------|
| **Catcher** | Browser-JS-Modul je Plattform (`Wp_Sdtrk_Catcher_*`), das Events „einfängt" und an Pixel und/oder Backend weitergibt. |
| **Tracker** | Server-seitige PHP-Klasse je Plattform (`Wp_Sdtrk_Tracker_*`), die das Event an die Conversion-API sendet. |
| **Engine** | Zentrales Browser-Orchestrierungs-Objekt (`Wp_Sdtrk_Engine`), das alle Catcher steuert. |
| **Handler** | Event-Kategorie: `Page`, `Event`, `Scroll`, `Time`, `Click`, `Visibility`. |
| **Signal-Event** | Kein Kauf/Lead, sondern ein Engagement-Signal (Scroll/Time/Click/Visibility). |
| **`type`** | Plattform-Kürzel im AJAX-Payload (`meta`, `ga`, `tt`, …), das den Server-Tracker auswählt. |
| **fbp/fbc** | Meta-Cookies/Click-IDs für Browser↔Server-Matching. |
| **cid** | GA4 Client-ID. |
| **ttc/ttp** | TikTok Click-ID bzw. User-Cookie. |
| **Mapping** | LinkedIn: Zuordnung Event → Conversion-ID mit optionalen Regeln. |
| **Deduplizierung** | Gemeinsame `event_id` für Browser- und Server-Event, damit Plattformen Doppelzählungen erkennen. |

## Lesereihenfolge-Empfehlung

1. [01 Architektur](01-architecture/README.md) — wie das Plugin aufgebaut ist.
2. [02 Server-Tracking](02-server-tracking/README.md) — der Kern (Conversion API).
3. [03 Browser-Tracking](03-browser-tracking/README.md) — die Datenquelle im Browser.
4. [04 Admin & Optionen](04-admin-and-options/README.md) — Konfiguration.
5. [05 Datenmodell](05-data-model/README.md) & [06 Integrationen](06-integrations.md).
6. [99 Befunde](99-findings.md) — was beim Weiterarbeiten zu beachten ist.
