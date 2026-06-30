# 03 — Event-Erfassung im Browser

Datei: `public/js/wp-sdtrk-event.js`, Klasse `Wp_Sdtrk_Event` (Datencontainer) + `collect_eventData()` der Engine.

## 1. Datenquellen

| Quelle | Mechanismus | Beispiel |
|--------|-------------|----------|
| **URL-Parameter (GET)** | `helper.get_Param()` / `pmap`-Aliase | `?prodid=SKU123&type=purchase&email=…` |
| **Cookies (Erstpartei)** | `helper.get_Cookies()` | persistente UTM-Werte (`wpsdtrk_utm_*`) |
| **Server-Localize** | `wp_sdtrk_engine.*` | `pageId`, `pageTitle`, `prodId`, `addr`, `agent` |
| **Metabox** | über Localize gespiegelt | `wp_sdtrk_product_id`, `wp_sdtrk_bypass_consent` |
| **WooCommerce-Commerce** | `wp_sdtrk_wc.{order\|beginCheckout\|addToCart\|viewItem}` (genau eine Quelle/Load, Präzedenz `order > beginCheckout > addToCart > viewItem`) | Purchase: `orderId`/`value`/`currency`/Käuferdaten/`items[]` ([07 › Purchase](../07-woocommerce/purchase-tracking.md)); InitiateCheckout: `value`/`currency`/`items[]` ([07 › InitiateCheckout](../07-woocommerce/initiate-checkout.md)); AddToCart/ViewItem: `value`/`currency`/`items[]` ([07 › ViewItem & AddToCart](../07-woocommerce/view-item-and-add-to-cart.md)) |
| **DOM** | `collect_items()` | CSS-Klassen `.trkbtn-*`, `.watchitm-*` |

> **Parameter-Aliase (`pmap`):** Mehrere URL-Schreibweisen werden auf ein kanonisches Feld gemappt (z. B. `type`/`eventtype` → Event-Typ). Das Mapping kommt aus PHP (`WP_SDTRK_Helper_Event`).

## 2. Felder des Event-Objekts

```
// Identität / Zeit
eventId, eventTime, eventTimeHour, eventTimeDay, eventTimeMonth

// Event
eventName, orderId, prodId, prodName, value, currency
items: [ { id, name, qty, price }, … ]   // ganzer Warenkorb (WooCommerce-Purchase)

// User (werden nach dem Auslesen aus der URL entfernt)
userFirstName, userLastName, userEmail, userFP

// Kontext
eventSource, eventSourceAgent, eventSourceAdress, eventSourceReferer,
eventUrl, eventDomain, eventPath

// Kampagne
utm_source, utm_medium, utm_campaign, utm_content, utm_term
```

> **Datenschutz-Detail:** Personenbezogene URL-Parameter (`email`, Vor-/Nachname) werden nach dem Einlesen aus der sichtbaren URL **entfernt** (History-Replace), damit sie nicht in der Adresszeile / im Referrer stehen bleiben.

## 3. Event-Trigger im Browser

| Trigger | Aktivierung | Konfiguration |
|---------|-------------|---------------|
| **Page View** | sofort bei `run()` | immer |
| **Time** | `setTimeout` je Zeitwert | `timeTrigger[]` (Sekunden) |
| **Scroll** | Scroll-Listener, Schwellen | `scrollTrigger[]` (Prozent) |
| **Click** | Delegierter Klick auf `.trkbtn-*` | `clickTrigger` |
| **Visibility** | Element im Viewport (`.watchitm-*`) | `visibilityTrigger` |

### DOM-Konventionen

```html
<button class="trkbtn-button_purchase">Jetzt kaufen</button>
<section class="watchitm-section_testimonials">…</section>
```

Der Teil nach dem Präfix (`button_purchase`, `section_testimonials`) wird als **Tag** an Click-/Visibility-Events übergeben und u. a. für LinkedIn-Mapping und Event-IDs genutzt.

## 4. Deduplizierung im Browser

- **Click:** Tag wird in `wp_sdtrk_clickedBtns` gemerkt → max. 1× pro Tag.
- **Scroll:** erreichte Prozentschwelle in `wp_sdtrk_catchedScrolls`.
- **Visibility:** Element-Tag in `wp_sdtrk_visitedItems`.

Die zugehörige `event_id` wird beim Senden an den Server entsprechend mit Suffix versehen (siehe [02 › Deduplizierung](../02-server-tracking/user-data-deduplication.md#3-event-deduplizierung)).
