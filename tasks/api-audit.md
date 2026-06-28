# API-Audit (T1.0) — externe Integrationen vs. aktueller Anbieter-Stand

Stand der Recherche: Juni 2026. Quellen-getrieben. Pro Integration: Ist (Code) · Soll (aktuell) · Handlungsbedarf.

---

## 1. Meta / Facebook Conversions API — **Update nötig**

| | |
|---|---|
| **Ist (Code)** | `https://graph.facebook.com/v11.0/{pixel}/events?access_token=…` ([class-wp-sdtrk-tracker-meta.php:57](../public/class-wp-sdtrk-tracker-meta.php#L57)) |
| **Soll** | Aktuelle Graph-API-Version. Neueste: **v25.0** (18.02.2026); aktiv ohne Ablaufdatum: v21–v25. v11.0 ist lange deprecated. |
| **Payload** | Struktur unverändert gültig: `data[]` mit `event_name`, `event_time`, `event_id`, `event_source_url`, `action_source:"website"`, `user_data` (gehasht em/fn/ln + ip/ua/fbp/fbc), `custom_data`. **Kein Payload-Umbau nötig.** |
| **Auth** | `access_token` als Query-Param — unverändert. |
| **Handlungsbedarf** | **Ja** — nur Versions-String anheben. Gewählt: **v23.0** (29.05.2025) als stabile, aktuelle Generation mit ~2 Jahren Runway; bewusst nicht die brandneue v25.0. |

Quellen: [Graph API Versions](https://developers.facebook.com/docs/graph-api/changelog/versions), [CAPI Docs](https://developers.facebook.com/docs/marketing-api/conversions-api/).

---

## 2. TikTok Events API — **Migration nötig (v1.2 → v2.0/v1.3)**

| | |
|---|---|
| **Ist (Code)** | `https://business-api.tiktok.com/open_api/v1.2/pixel/track/` ([class-wp-sdtrk-tracker-tt.php:57](../public/class-wp-sdtrk-tracker-tt.php#L57)) — Legacy „Pixel"-Events-API. |
| **Soll** | **Events API 2.0**: `https://business-api.tiktok.com/open_api/v1.3/event/track/` |
| **Auth** | `Access-Token`-Header — unverändert. |
| **Payload-Umbau** | Struktureller Umbau (siehe unten). |

**Payload-Mapping v1.2 → v1.3:**

| v1.2 (Ist) | v1.3 (Soll) |
|---|---|
| `pixel_code` | `event_source_id` + neues `event_source: "web"` |
| Event-Objekt top-level | in `data: [ {…} ]` gekapselt |
| `timestamp` (ISO-8601, `date('c')`) | `event_time` (Unix-Sekunden, Integer) |
| `context.page` | `page` (im data-Item) |
| `context.ip`, `context.user_agent` | `user.ip`, `user.user_agent` |
| `context.ad.callback` (ttclid) | `user.ttclid` |
| `context.user.email` (sha256) | `user.email` (sha256) — unverändert |
| `context.user.external_id` (= ttc, ungehasht) | entfällt; ttc → `user.ttclid`, zusätzlich `user.ttp` aus `_ttp` |
| `properties.contents[]` (content_id/name/type/quantity/price) | unverändert in `properties` |
| `properties.currency/value/description` | unverändert |
| `test_event_code` (im Event) | `test_event_code` (top-level, neben `data`) |

**Handlungsbedarf:** **Ja** — Endpoint + Payload-Restrukturierung. Event-Namens-Mapping (`convert_eventname`) bleibt unverändert (ViewContent/PlaceAnOrder/…). Die `debugEvent()`-Beispielmethode (toter v1.2-Code) wird mit migriert oder entfernt.

Quellen: [About Events API](https://ads.tiktok.com/help/article/events-api), [Events API supported events v1.3](https://business-api.tiktok.com/portal/docs/supported-events/v1.3), [Stape TikTok Events API](https://stape.io/helpdesk/documentation/tiktok-events-api).

---

## 3. GA4 Measurement Protocol — **kein Update nötig**

| | |
|---|---|
| **Ist (Code)** | `https://www.google-analytics.com/mp/collect?api_secret=…&measurement_id=…` (+ Debug-Endpoint `/debug/mp/collect`) ([class-wp-sdtrk-tracker-ga.php:56-57](../public/class-wp-sdtrk-tracker-ga.php#L56)) |
| **Soll** | Identisch. MP ist ein ausgereiftes, finalisiertes Produkt ohne Deprecation-Plan. |
| **Payload** | `client_id` + `events[]` (`name`+`params`) — gültig. |
| **Handlungsbedarf** | **Nein** (Endpoint/Auth aktuell). |
| **Empfehlung (optional, nicht Teil dieser Phase)** | Für korrekte Session-/Engagement-Zuordnung in Standardberichten `session_id` + `engagement_time_msec` je Event mitgeben. Würde Reporting-Verhalten ändern → separat entscheiden, hier **nicht** umgesetzt. |

Quellen: [MP Reference](https://developers.google.com/analytics/devguides/collection/protocol/ga4/reference), [MP Overview](https://developers.google.com/analytics/devguides/collection/protocol/ga4).

---

## 4. Browser-Pixel — **größtenteils aktuell; 1 Punkt zur Live-Verifikation**

| Plattform | Script / API (Code) | Bewertung |
|---|---|---|
| Meta | `connect.facebook.net/en_US/fbevents.js` · `fbq()` | aktuell ✓ |
| GA4 | `gtag()` | aktuell ✓ |
| TikTok | `analytics.tiktok.com/i18n/pixel/events.js` · `ttq` | aktuell ✓ |
| LinkedIn | `snap.licdn.com/li.lms-analytics/insight.min.js` · `lintrk()` | aktuell ✓ |
| Mautic | `{url}/mtc.js` · `mt()` | aktuell ✓ (Standard) |
| Matomo | `_paq.push()` · `matomo.php` | aktuell ✓ (Standard) |
| **Funnelytics** | `cdn.funnelytics.io/**track-v3.js**` · `window.funnelytics.events.trigger()` | **prüfen** — offizielle Basis ist heute `cdn.funnelytics.io/track.js` + `window.funnelytics.init(...)`. `track-v3.js`/`events.trigger` kann die neuere Variante sein; **nicht blind ändern**, Live-Test erforderlich. |

**Handlungsbedarf:** Nur Funnelytics offen (Live-Verifikation, kein blinder Code-Change). Rest: kein Update.

Quellen: [Funnelytics Base Script](https://hub.funnelytics.io/c/tracking-setup/base-script-install).

---

## Zusammenfassung Handlungsbedarf

| Integration | Aktion | Task |
|---|---|---|
| Meta CAPI | Version `v11.0` → `v23.0` | T1.1 |
| TikTok | Migration v1.2 `pixel/track` → v1.3 `event/track` (Endpoint + Payload) | T1.2 |
| GA4 MP | keine (verifiziert aktuell) | T1.3 |
| Browser-Pixel | keine, außer Funnelytics-Live-Check | T1.4 |
</content>
