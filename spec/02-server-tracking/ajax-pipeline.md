# 02 — AJAX-Pipeline (Browser → Server-Tracker)

Beschreibt den Weg eines Events vom Browser bis zum Aufruf der Conversion-API.

## 1. Eintritt: `Wp_Sdtrk_Public_Ajax_Handler::handle_public_ajax_callback()`

Gebunden an beide Hooks (`wp_ajax_…` und `wp_ajax_nopriv_…`), damit auch **nicht eingeloggte** Besucher tracken können.

Ablauf:

1. **Nonce-Prüfung** (Pflicht):
   ```php
   if (! wp_verify_nonce($_POST['_nonce'], 'security_wp-sdtrk')) { … die(); }
   ```
2. **Methoden-Whitelist über `func`:**
   ```php
   $functionName = $_POST['func'];
   if (! method_exists($this, $functionName)) { … }
   $result = $this->$functionName($_POST['data'], $_POST['meta']);
   ```
   → Es sind nur Methoden aufrufbar, die auf dem Handler existieren. Aktuell relevant: **`validateTracker`**.
3. Ergebnis wird als JSON zurückgegeben.

> **Design-Hinweis:** Das `func`-Dispatch ist ein generisches „RPC"-Muster. Sicherheit hängt allein an `method_exists` + Nonce; es gibt keine Capability-Prüfung (per Design, da Tracking öffentlich ist). Siehe [99 Befunde](../99-findings.md) zur Eingabe-Sanitisierung.

## 2. `validateTracker($data, $debugMode = false)`

```php
// Pflichtfelder
if (! isset($data['event'], $data['type'], $data['handler'], $data['data'])) return ['state' => false];

$event     = new Wp_Sdtrk_Tracker_Event($data['event']);
$className  = 'Wp_Sdtrk_Tracker_' . ucfirst($data['type']);   // 'meta' → 'Wp_Sdtrk_Tracker_Meta'
if (class_exists($className)) {
    $tracker = new $className();
    if (method_exists($tracker, 'fireTracking_Server') && method_exists($tracker, 'setAndGetDebugMode_frontend')) {
        return [
            'debug' => $tracker->setAndGetDebugMode_frontend($debugMode),
            'state' => $tracker->fireTracking_Server($event, $data['handler'], $data['data']),
        ];
    }
}
return ['state' => false, 'debug' => false];
```

### 2.1 `type` → Klassen-Mapping

| `type` (vom Catcher) | erwarteter Klassenname | tatsächliche Klasse | Ergebnis |
|----------------------|------------------------|---------------------|----------|
| `ga` | `Wp_Sdtrk_Tracker_Ga` | `Wp_Sdtrk_Tracker_Ga` | ✅ trifft |
| `tt` | `Wp_Sdtrk_Tracker_Tt` | `Wp_Sdtrk_Tracker_Tt` | ✅ trifft |
| `meta` | `Wp_Sdtrk_Tracker_Meta` | **`Wp_Sdtrk_Tracker_Fb`** | ❌ `class_exists` = false |

> ⚠️ **Bestätigter Befund:** Der Meta-Catcher sendet `type: 'meta'` (`wp-sdtrk-meta.js`, `sendData()`), woraus `Wp_Sdtrk_Tracker_Meta` gebildet wird. Die Klasse heißt aber `Wp_Sdtrk_Tracker_Fb` und es existiert **kein** `class_alias`. Dadurch liefert `validateTracker` für Meta `state => false` und die **Meta-CAPI feuert serverseitig nie**. Vollständige Analyse: [99 Befunde › Meta-CAPI-Dispatch](../99-findings.md#meta-capi-dispatch).

## 3. Handler-Typen

`fireTracking_Server($event, $handler, $data)` verzweigt je nach `$handler` in plattformspezifische Methoden:

| `handler` | Bedeutung | typische Server-Methode |
|-----------|-----------|--------------------------|
| `Page` | Pageview | `fireTracking_Server_Page()` |
| `Event` | Conversion (purchase/lead/…) | `fireTracking_Server_Event()` |
| `Scroll` | Scroll-Tiefe erreicht | `fireTracking_Server_Scroll()` |
| `Time` | Zeit auf Seite erreicht | `fireTracking_Server_Time()` |
| `Click` | Button-Klick | `fireTracking_Server_Click()` |
| `Visibility` | Element sichtbar | `fireTracking_Server_Visibility()` |

## 4. Payload-Struktur (vom Browser)

```jsonc
{
  "action": "wp_sdtrk_handle_public_ajax_callback",
  "_nonce": "…",
  "func":   "validateTracker",
  "meta":   false,                 // debugMode
  "data": {
    "event":   { /* gesamtes Event-Objekt, siehe event-model.md */ },
    "type":    "meta",             // Plattform
    "handler": "Event",            // Event-Kategorie
    "data": {                      // plattform-/handler-spezifische Zusatzdaten
      "fbp": "fb.1.…", "fbc": "fb.1.…",   // Meta
      "cid": "GA1.1.…", "gclid": "…",     // GA
      "ttc": "…", "ttp": "…", "hash": "…", // TikTok
      "percent": 50,               // Scroll
      "time": 10,                  // Time
      "tag": "button_purchase",    // Click/Visibility
      "state": true
    }
  }
}
```

> Das Event-Objekt (`data.event`) wird im Browser gesammelt (siehe [03 › Event-Erfassung](../03-browser-tracking/event-collection.md)) und im PHP-Tracker über `Wp_Sdtrk_Tracker_Event` gekapselt.
