# 03 — Consent-Management

Datei: `public/js/wp-sdtrk-helper.js`, Methode `has_consent(id, service, event)`.

## 1. Unterstützte Consent-Lösung: Borlabs Cookie

Das Plugin prüft vor Pixel-Laden/Server-Senden den Consent. Aktuell ist **Borlabs Cookie** der einzige explizit unterstützte Dienst — sowohl **v2** als auch **v3** (v3-Kompatibilität in v1.7.6 ergänzt, Commits `f318c97`/`ad0481a`).

```js
has_consent(id, service, event) {
  if (event.getForce()) return -1;        // Force/Bypass → Consent übersprungen
  switch (service) {
    case 'borlabs':
      // Borlabs v2
      if (typeof window.BorlabsCookie.checkCookieConsent === "function")
        return window.BorlabsCookie.checkCookieConsent(id);
      // Borlabs v3
      if (typeof window.BorlabsCookie.Consents?.hasConsent === "function")
        return window.BorlabsCookie.Consents.hasConsent(id);
      return -1;
    default:
      return -1;                           // unbekannter/kein Dienst → kein Block
  }
}
```

Rückgaben: `true`/`false` (Consent erteilt/abgelehnt) bzw. `-1` (keine Aussage → wird nicht blockiert).

## 2. Konfiguration je Plattform

Pro Plattform und je getrennt für Browser/Server (siehe [04 › Options-Referenz](../04-admin-and-options/option-reference.md)):

| Localize-Feld | Option | Bedeutung |
|---------------|--------|-----------|
| `b_cs` / `s_cs` | `*_trk_browser_cookie_service` / `*_trk_server_cookie_service` | `none` oder `borlabs` |
| `b_ci` / `s_ci` | `*_trk_browser_cookie_id` / `*_trk_server_cookie_id` | Borlabs-Cookie-ID (z. B. `facebook`) |
| `b_e` / `s_e` | abgeleitet | Browser- bzw. Server-Tracking aktiv |

## 3. Force- / Bypass-Modus

Auf Seitenebene kann Consent **umgangen** werden (z. B. interne Thank-You-Pages):

```js
// engine.js
if (this.localizedData.trkow !== "") this.event.enableForce();
else this.event.disableForce();
```

`trkow` (Tracking-Overwrite) stammt aus der Metabox-Option `wp_sdtrk_bypass_consent` der jeweiligen Seite (siehe [04 › Metabox](../04-admin-and-options/metabox-and-helpers.md)). Bei aktivem Force liefert `has_consent` immer `-1` → es wird unabhängig vom Consent getrackt.

## 4. Backload bei nachträglichem Consent

Events werden in `wp_sdtrk_history` gehalten, sodass bei späterer Zustimmung zuvor blockierte Events nachgespielt werden können (Backload-Mechanik der Engine).
