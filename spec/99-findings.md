# 99 — Befunde & offene Punkte

Beobachtungen aus der Quellcode-Analyse (Stand v1.7.6). Eingeteilt nach Schweregrad. Dies ist eine **Ist-Zustand-Dokumentation** — die Punkte sind als Hinweise für die Weiterentwicklung gedacht, nicht als beauftragte Änderungen.

Legende: 🔴 funktionaler Bug · 🟡 Auffälligkeit/Hygiene · 🔵 Hinweis/Information

---

## 🔵 Mautic: Custom-Event-Erfassung setzt ein Mautic-Plugin voraus (by design)

**Verifiziert.** `Wp_Sdtrk_Catcher_Mtc::fireData()` sendet Events via `mt('send', '<eventName>', {…})` mit echten Event-Namen (`purchase`, `view_item`, …). Natives MauticJS verarbeitet nur `mt('send', 'pageview', {…})` (Core prüft `type === 'pageview'`); zusätzliche Event-Typen werden von Plugins/Bundles über `CoreEvents::BUILD_MAUTIC_JS` (`appendJs`) in `mtc.js` injiziert (Mautic-Core-PR-Hinweis: „plugins/bundles can implement more tracking events"). Die Custom-Event-Erfassung des Catchers setzt daher ein entsprechendes **Mautic-seitiges Plugin** (z. B. „Mautic Custom Events") voraus. Bewusst **nicht** auf `pageview` umgebaut — Käufe/Events als PageView zu tracken wäre semantisch falsch. **Voraussetzung dokumentieren** (Mautic-Plugin nötig); der `pageview`-Hit selbst funktioniert nativ.

---

## 🔵 Funnelytics: optionales `init`-Argument `anonymiseUsers` wird nicht übergeben

Der Funnelytics-Catcher (`Wp_Sdtrk_Catcher_Fl`) ist **verifiziert API-konform**: Base-Snippet (CDN `https://cdn.funnelytics.io/track-v3.js`, Deferred-Queue, `funnelytics.init(funnel, false, deferredEvents)`) und Commerce-Keys (`__commerce_action__`, `__total_in_cents__` in Cent, `__sku__`, `__order__`, `__currency__`, `__label__`) entsprechen dem offiziellen Snippet bzw. der [Revenue-Actions-Doku](https://hub.funnelytics.io/c/tracking-setup/base-script-install). Die als „Funnelytics Tracking ID" hinterlegte `fl_tracking_id` muss die **Workspace-UUID** sein (wird als `funnel` durchgereicht).

Neuere Workspace-Snippets hängen ein viertes `init`-Argument an (`{"anonymiseUsers": false}`); der Catcher übergibt es nicht. Ohne Wirkung auf den aktuellen Payload, da der Catcher **keine** `name`/`email`-Keys sendet — relevant erst, falls De-Anonymisierung über Funnelytics genutzt werden soll.

---

## 🔵 Cron seit Produkt-Feed aktiv

`WP_SDTRK_Cron` ist seit der WooCommerce-Produkt-Feed-Funktion **aktiv** (täglicher Hook `wp_sdtrk_cron_generate_feed`, siehe [07 › Produkt-Feed](07-woocommerce/product-feed.md)). Die im README erwähnten früheren Sync-Features (CSV/Google-Sheet/Live-Feed) sind davon unabhängig und nicht implementiert.

---

## 🔵 Produkt-Feed: Token in der URL — by design

Das Feed-Token wird als `?token=…`-Query-Parameter übertragen (von Google/Meta vorgegeben, die nur **eine** URL akzeptieren) und kann so in Server-/Proxy-/CDN-Logs landen. Die Feed-URL ist daher als Geheimnis zu behandeln. Ein Admin-Button erlaubt die Token-Rotation ([07 › Produkt-Feed](07-woocommerce/product-feed.md)); eine Übertragung außerhalb der URL ist anbieterseitig nicht möglich.

---

## 🔵 SHA256-Hashing ohne Salt — by design

E-Mail/Name werden mit reinem SHA256 (ohne Salt/HMAC) gehasht. Das ist **kein Bug**: Meta und TikTok verlangen exakt dieses Format, um die übermittelten Hashes mit ihren eigenen abzugleichen. Ein Salt würde das Matching verhindern. Dokumentiert wegen des Rainbow-Table-Themas bei E-Mail-Adressen.

---

## 🔵 Composer-PSR-4 verweist auf nicht vorhandenes `src/`

`composer.json` deklariert `Rankt\WpSmartServerSideTracking\` → `src/`, aber das Verzeichnis existiert nicht. Der Plugin-Code nutzt `require_once` statt Autoloading; der Namespace ist (derzeit) ungenutzt. Möglicher Hinweis auf eine geplante, nicht abgeschlossene Migration.

---

> Aktuell sind keine offenen 🟡/🔴-Punkte verzeichnet — die verbleibenden Einträge sind 🔵-Hinweise (by design / Information).
