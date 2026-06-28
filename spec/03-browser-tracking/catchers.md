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
