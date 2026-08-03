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

## 5. Consent Mode v2 (Google-Tag)

Nur der GA-Catcher (`wp-sdtrk-ga.js`) sendet zusätzlich zum Blockieren die vier Einwilligungssignale des Google Consent Mode v2 (`analytics_storage`, `ad_storage`, `ad_user_data`, `ad_personalization`). Ohne das Signal `ad_user_data` stellt Google den **Conversion-Export von GA4 nach Google Ads ein** — Analytics erfasst die Käufe weiterhin, in Ads bleibt die Conversion-Spalte auf 0.

### Basic Consent Mode

Das Tag wird **bis zur Einwilligung vollständig blockiert**; vor der Zustimmung geht kein Request an Google. Die Signale werden erst beim Laden des Tags gesetzt. Reihenfolge in `loadPixel()`:

```
dataLayer + gtag-Shim anlegen
  → set_consentMode()      // 'default' (alles denied) + 'update' (alles granted)
  → gtag.js injizieren
  → gtag('js') / gtag('config')
```

Die Consent-Kommandos müssen **vor** dem `config`-Kommando in der `dataLayer` stehen, sonst hat sich das Tag bereits konfiguriert, wenn sie eintreffen.

### Signal-Herleitung

Alle vier Signale sind `granted`. Der Catcher erreicht `loadPixel()` ausschließlich mit erteiltem Consent (bzw. bei Cookie-Service `none`, wo er per Konfiguration bedingungslos feuert) — dieselbe Einwilligung deckt die Werbenutzung mit ab. Eine getrennte Werbe-Einwilligung kennt das Plugin bewusst nicht.

Das vorangestellte `default` mit durchgehend `denied` bleibt trotzdem: Google erwartet vor jedem Mess-Kommando einen expliziten Ausgangszustand.

### Fremde Consent-Mode-Implementierung

`has_externalConsentMode()` durchsucht die `dataLayer` nach einem bereits vorhandenen `consent`-Kommando. Wird eines gefunden (z. B. weil der Consent-Manager den Consent Mode selbst verwaltet), setzt das Plugin **weder** `default` **noch** `update`. So streiten sich nie zwei Quellen um denselben Zustand.
