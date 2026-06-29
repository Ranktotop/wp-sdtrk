# 03 — Catcher-Module

Jede Plattform hat im Browser eine Catcher-Klasse `Wp_Sdtrk_Catcher_*`. Sie kapselt: Pixel laden, Consent prüfen, Events einfangen und über zwei Wege ausgeben.

## 1. Gemeinsames Interface (Konvention)

| Methode | Aufgabe |
|---------|---------|
| `validate(target)` | Consent prüfen, Pixel laden (`target`: 0=Browser, 1=Server, 2=beide) |
| `loadPixel()` | natives Tag/Pixel injizieren |
| `catchPageHit / catchEventHit / catchScrollHit / catchTimeHit / catchClickHit / catchVisibilityHit` | Event-spezifische Einstiegspunkte |
| `fireData(handler, data)` | natives Pixel auslösen |
| `sendData(handler, data)` | AJAX an Server-Tracker (`type` gesetzt) |

## 2. Plattform-Spezifika

| Catcher | Natives API | `type` (Server) | Server? | Besonderheit |
|---------|-------------|-----------------|:------:|--------------|
| `Meta` | `fbq()` | `meta` | ✅ | verwaltet `_fbp`/`_fbc`; Pixel-Version `fb` |
| `Ga` | `gtag()` | `ga` | ✅ | liefert `cid`/`gclid` |
| `Tt` | `ttq()` | `tt` | ✅ | `ttc`/`ttp`; Hash aus UA+IP |
| `Lin` | `lintrk()` | — | ❌ | Event→Conversion-ID-Mapping (siehe [05](../05-data-model/linkedin-mapping.md)) |
| `Fl` | `funnelytics.events.trigger()` | — | ❌ | SKU/Label-basiert |
| `Mtc` | `mt()` | — | ❌ | Mautic, Event-Name-basiert |
| `Mtm` | `_paq.push()` | — | ❌ | Matomo, Site-ID-basiert |

## 2a. Maßgebliche Anbieter-Dokumentation (immer beachten)

> ⚠️ **Verbindlich:** Natives Pixel-/Tag-Snippet, globale API (`fbq`/`gtag`/`ttq`/`lintrk`/`funnelytics.events`/`mt`/`_paq`), Event-Namen und Payload-Felder folgen **ausschließlich** der offiziellen Anbieter-Doku. Vor **jeder** Änderung an einem Browser-Payload oder Snippet die hier verlinkten Quellen prüfen — nicht aus dem Gedächtnis oder aus Sekundärquellen arbeiten. Snippet-URLs und API-Signaturen ändern sich anbieterseitig; veraltete Snippets laden nicht oder verwerfen Events still. Die server-seitigen Payloads (Meta/GA/TikTok) haben ihren eigenen verbindlichen Block: [02 › Maßgebliche Anbieter-Dokumentation](../02-server-tracking/README.md#maßgebliche-anbieter-dokumentation-immer-beachten).

| Catcher | Natives API | Offizielle Doku |
|---------|-------------|-----------------|
| `Meta` | `fbq()` | [Meta-Pixel-Referenz](https://developers.facebook.com/docs/meta-pixel/reference) · [Advanced Matching](https://developers.facebook.com/docs/meta-pixel/implementation/conversion-tracking#advanced-matching) |
| `Ga` | `gtag()` | [GA4 E-Commerce (gtag)](https://developers.google.com/analytics/devguides/collection/ga4/ecommerce) · [gtag.js-Referenz](https://developers.google.com/tag-platform/gtagjs/reference) |
| `Tt` | `ttq()` | [TikTok About Events API](https://ads.tiktok.com/help/article/events-api) · [Parameter](https://ads.tiktok.com/help/article/about-parameters) |
| `Lin` | `lintrk('track', { conversion_id })` | [LinkedIn Insight-Tag Conversion-Tracking](https://learn.microsoft.com/en-us/linkedin/marketing/integrations/ads-reporting/conversion-tracking) · [Insight-Tag-Conversions einrichten](https://www.linkedin.com/help/lms/answer/a425606) |
| `Fl` | `funnelytics.events.trigger(name, props)` | [Funnelytics — Base-Script + Custom/Revenue Actions](https://hub.funnelytics.io/c/tracking-setup/base-script-install) · [Tracking JavaScript Actions](https://help.funnelytics.io/en/knowledge/tracking-javascript-actions) |
| `Mtc` | `mt('send', 'pageview', …)` (MauticJS `mtc.js`) | [Mautic — Tracking Script (mtc.js)](https://devdocs.mautic.org/en/5.x/components/tracking_script.html) |
| `Mtm` | `_paq.push(['trackEvent'/'trackGoal', …])` | [Matomo JS-Tracking-API-Referenz](https://developer.matomo.org/api-reference/tracking-javascript) · [JS-Tracking-Guide](https://developer.matomo.org/guides/tracking-javascript-guide) |

## 3. `sendData`-Beispiel (Meta)

```js
sendData(handler, data) {
  // … 'b' = Browser, 's' = Server
  this.helper.send_ajax(
    { event: this.event, type: 'meta', handler: handler, data: data },
    this.localizedData.dbg
  );
}
```

→ landet in `Wp_Sdtrk_Public_Ajax_Handler::validateTracker` (siehe [02 › AJAX-Pipeline](../02-server-tracking/ajax-pipeline.md)).

## 4. LinkedIn-Catcher (Conversion-Mapping)

`Wp_Sdtrk_Catcher_Lin.get_triggeredConversions(eventName)`:
1. iteriert über `localizedData.map_ev` (Event→ConvID-Mappings),
2. vergleicht Event-Name und prüft die hinterlegten **Rules** (z. B. `prodid`, `prodname`),
3. gibt passende Conversion-IDs zurück → `lintrk('track', { conversion_id })`.

Zusätzlich `map_btn` (Button-Tags) und `map_iv` (Visibility-Tags). Datenherkunft: DB-Tabelle `sdtrk_linkedin`, ausgespielt via Localize. Komplette Beschreibung: [05 › LinkedIn-Mapping](../05-data-model/linkedin-mapping.md).
