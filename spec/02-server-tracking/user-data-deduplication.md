# 02 — User-Daten, Hashing & Deduplizierung

Querschnittsthemen, die alle Server-Tracker betreffen. Gemeinsame Helfer liegen in `WP_SDTRK_Helper_Event`.

## 1. User-Daten-Erfassung

### IP-Adresse — `WP_SDTRK_Helper_Event::getClientIp()`

Priorität:
1. `$_SERVER['HTTP_CLIENT_IP']`
2. `$_SERVER['HTTP_X_FORWARDED_FOR']`
3. `$_SERVER['REMOTE_ADDR']`

Verwendung: Meta `client_ip_address`, TikTok `context.ip`. (GA4 MP ermittelt die IP serverseitig selbst.)

### User-Agent

Priorität: `event.eventSourceAgent` (vom Browser) → `$_SERVER['HTTP_USER_AGENT']` → `""`.

### Click-IDs & Cookies (vom Browser im `data`-Sub-Array)

| Feld | Plattform | Quelle |
|------|-----------|--------|
| `fbp` | Meta | `_fbp`-Cookie |
| `fbc` | Meta | `_fbc` (aus `fbclid`) |
| `cid` | GA4 | GA4-Client-ID |
| `gclid` | GA4/Ads | URL-Parameter |
| `ttc` | TikTok | `ttclid` |
| `ttp` | TikTok | TikTok-Cookie |

Helper `getGetParamWithCookie($name, $firstParty=true)` liest GET-Parameter bzw. Erstpartei-Cookie (`wpsdtrk_{name}`).

## 2. Hashing

- **Algorithmus:** SHA256 (`hash('sha256', $value)`), **ohne Salt/HMAC**.
- **Meta:** `em`, `fn`, `ln`.
- **TikTok:** `email`.
- **GA4:** kein Hashing (überträgt keine personenbezogenen Identitätsfelder).

> ⚠️ Reines SHA256 ohne Salt ist für E-Mail/Name das von Meta/TikTok **geforderte** Format (die Plattformen hashen ihre Seite identisch). Es ist also kein Bug, aber bewusst zu dokumentieren (Rainbow-Table-Thema). Siehe [99 Befunde](../99-findings.md).

## 3. Event-Deduplizierung

Ziel: Browser-Pixel-Event und Server-Event tragen dieselbe Identität, damit die Plattform Doppelzählungen erkennt.

### Basis-ID — `event.getEventId()`

Priorität: `eventData.eventId` → `orderId` → generiert (`substr(str_shuffle(md5(microtime())), 0, 10)`). Im Browser wird sie als `Math.floor(random*100) + Date.now()` gebildet.

### Suffixe für Signal-Events

Damit jedes Signal eindeutig bleibt, wird die Basis-ID erweitert:

| Handler | Suffix-Schema |
|---------|---------------|
| Scroll | `…-s{percent}` |
| Time | `…-t{time}` |
| Click | `…-b{tag}` |
| Visibility | `…-v{tag}` |

### Plattform-Felder

| Plattform | Feld für Deduplizierung |
|-----------|-------------------------|
| Meta | `data[].event_id` |
| GA4 | `params.transaction_id` |
| TikTok | `event_id` = `{basis}_{hash}` (Browser-Hash zusätzlich) |

## 4. Debug-Modus

Jeder Tracker hat `setAndGetDebugMode_frontend($debugMode)`. Aktiviert ergänzt er den jeweiligen Test-Event-Code (`*_trk_server_debug_code`) bzw. den Debug-Endpoint (GA4) und gibt Debug-Infos an das Frontend zurück (`['debug' => …]`).
