# 03 — Engine & Lebenszyklus

Datei: `public/js/wp-sdtrk-engine.js`, Klasse `Wp_Sdtrk_Engine`.

## 1. Start-Sequenz

```
1. Decrypter.decrypt()                 (wp-sdtrk-decrypter.js)
      ├─ GET-Parameter → Objekt (paramsToObject)
      ├─ wenn Decryption-Services aktiv (z. B. ds24): AJAX-Entschlüsselung auf dem Server
      └─ ruft wp_sdtrk_startEngine(decrypter)

2. wp_sdtrk_startEngine(decrypter)     (bei DOM-ready)
      └─ new Wp_Sdtrk_Engine(decryptedData)

3. Konstruktor
      ├─ new Wp_Sdtrk_Event()          Event-Container
      ├─ new Wp_Sdtrk_Helper()         AJAX/Cookies/Consent
      ├─ new Wp_Sdtrk_Fp()             Fingerprinting
      ├─ je 1 Catcher: Meta, Ga, Tt, Lin, Fl, Mtc, Mtm
      ├─ collect_eventData()           UTMs, prodId, email, value, …
      └─ collect_items()               DOM-Scan nach .trkbtn-* und .watchitm-*

4. engine.run()
      ├─ catchPageHit()                sofortiger Pageview an alle Catcher
      ├─ catchTimeHit()                setTimeout je konfiguriertem Zeitwert
      ├─ catchClickHit()               Klick-Listener auf .trkbtn-*
      ├─ catchScrollHit()              Scroll-Listener (Prozent-Schwellen)
      └─ catchVisibilityHit()          Viewport-Beobachtung für .watchitm-*
```

## 2. Globale Zustände (window-scope)

| Variable | Zweck |
|----------|-------|
| `wp_sdtrk_engine_class` | Engine-Instanz |
| `wp_sdtrk_scrollDepths` / `wp_sdtrk_catchedScrolls` | bereits ausgelöste Scroll-Schwellen |
| `wp_sdtrk_clickedBtns` | bereits getrackte Buttons (Deduplizierung) |
| `wp_sdtrk_visibilityItems` / `wp_sdtrk_visitedItems` | bereits getrackte Sicht-Elemente |
| `wp_sdtrk_history` | Event-Historie (Backload bei nachträglichem Consent) |

## 3. Catcher-Aktivierung (Consent-gesteuert)

Beim Erzeugen ruft jeder Catcher `validate(target)` mit `target ∈ {0:Browser, 1:Server, 2:beide}`. Geprüft wird der Consent (`helper.has_consent(cookieId, cookieService, event)`); bei Zustimmung wird das Pixel geladen (`loadPixel()`) und/oder Server-Tracking freigeschaltet. Details: [consent-management.md](consent-management.md).

## 4. Konfigurationsübergabe (Auszug `wp_sdtrk_engine`)

```jsonc
{
  "ajax_url": "/wp-admin/admin-ajax.php",
  "_nonce": "…",
  "admin": false,                 // current_user_can('manage_options')
  "prodId": "<metabox product id>",
  "trkow": "<bypass consent flag>",
  "pageId": 123, "pageTitle": "…",
  "rootDomain": "example.com", "currentDomain": "https://example.com/",
  "brandName": "…",
  "addr": "<client ip>", "agent": "<user agent>",
  "source": "…", "referer": "…",
  "timeTrigger":   [5, 10, 30],   // Sekunden
  "scrollTrigger": [25, 50, 75],  // Prozent
  "clickTrigger": "1", "visibilityTrigger": "1",
  "evmap": { /* Event-Name-Mapping */ },
  "pmap":  { /* Parameter-Alias-Mapping */ }
}
```

Pro Plattform existiert zusätzlich ein eigenes Global (`wp_sdtrk_meta`, `wp_sdtrk_ga`, …) mit Pixel-ID, Browser/Server-Flags und Consent-Einstellungen (`b_e/b_cs/b_ci`, `s_e/s_cs/s_ci`, `dbg`).
