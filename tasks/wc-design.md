# T2.0 — Design-Memo: WooCommerce-Integration

> Verbindliche Designentscheidungen für Phase 2 ([plan.md](plan.md) → T2.1–T2.6).
> Grundlage: bestätigte Architektur aus [public/class-wp-sdtrk-public.php](../public/class-wp-sdtrk-public.php),
> [public/class-wp-sdtrk-public-ajax.php](../public/class-wp-sdtrk-public-ajax.php),
> [public/class-wp-sdtrk-tracker-event.php](../public/class-wp-sdtrk-tracker-event.php),
> [public/class-wp-sdtrk-tracker-meta.php](../public/class-wp-sdtrk-tracker-meta.php).

## 0. Bestätigte Entscheidungen (Nutzer-Freigabe)

| Frage | Entscheidung |
|-------|--------------|
| **Feuer-Zeitpunkt** | **Browser-Pixel** auf `woocommerce_thankyou` (reload-entprellt). **Server-APIs** auf Order-Status-Übergang → `processing`/`completed`. Robust für asynchrone/Offline-Zahlungen (Vorkasse, Rechnung, SEPA). |
| **Consent (Server)** | **Consent-gated.** Server-Events feuern nur, wenn zum Bestellzeitpunkt Consent für den Dienst akzeptiert war — bypassbar über die bestehende Metabox `wp_sdtrk_bypass_consent` (bzw. globalen Bypass). Fail-closed: ohne gespeicherten Consent-Datensatz **kein** Server-Call. |
| **Dedup** | Gemeinsame `event_id` = **WooCommerce-Order-ID** für Browser- und Server-Event. `Wp_Sdtrk_Tracker_Event::getEventId()` liefert bereits die `orderId`, wenn gesetzt. |

## 1. Hooks

| Hook | Kontext | Aufgabe |
|------|---------|---------|
| `woocommerce_thankyou` (Prio 10, 1 Arg `$order_id`) | Browser (Order-Received-Seite) | 1) Browser-Purchase-Pixel injizieren (alle aktiven Plattformen). 2) **Consent-Snapshot + Browser-Identifier + IP/UA auf der Order persistieren** (Order-Meta), damit der spätere Server-Hook entscheiden/deduplizieren kann. 3) Reload-Entprellung über Order-Meta-Flag. |
| `woocommerce_order_status_processing` **und** `woocommerce_order_status_completed` (1 Arg `$order_id`) | Server (ggf. asynchron/später) | Server-APIs (Meta/GA4/TikTok) feuern, sofern Plattform aktiv **und** Consent-Snapshot akzeptiert (oder Bypass). Idempotent über Order-Meta-Flag je Plattform. |

**Warum zwei Status-Hooks:** Sofort-Zahlungen springen direkt auf `processing`/`completed`; asynchrone Zahlungen stehen beim Thankyou noch auf `on-hold`/`pending` und wechseln erst später. Beide Übergänge müssen das Server-Event auslösen — die Idempotenz-Flags verhindern Doppelversand.

## 2. Order-Meta-Schema (persistiert beim Thankyou)

| Meta-Key | Inhalt |
|----------|--------|
| `_wp_sdtrk_event_id` | Geteilte Event-ID = Order-ID (explizit, für Klarheit). |
| `_wp_sdtrk_consent` | Assoziatives Array `service => bool` (Consent-Status je Cookie-Service zum Bestellzeitpunkt) + `bypass => bool`. |
| `_wp_sdtrk_ids` | Browser-Identifier: `fbp`, `fbc`, `ttp`, `ttc`, `cid`/`gclid` (soweit vom Browser gemeldet). |
| `_wp_sdtrk_client` | `ip`, `agent` (aus dem Thankyou-Request — korrekter als der Admin-Request beim Status-Wechsel). |
| `_wp_sdtrk_browser_tracked` | `1`, sobald Browser-Pixel injiziert wurde (Reload-Entprellung). |
| `_wp_sdtrk_server_sent_{platform}` | `1` je Plattform nach erfolgreichem Server-Versand (Idempotenz). |

**Consent-Snapshot-Mechanik:** Browser-Consent wird in JS ermittelt (`helper.has_consent`, Borlabs v2/v3). Da der Server-Hook keinen Browser-Kontext hat, sendet das Thankyou-JS den Consent-Status + Identifier per bestehendem AJAX (`admin-ajax.php`, Nonce `security_wp-sdtrk`) an eine neue Public-AJAX-Funktion, die sie als Order-Meta speichert. Alternativ — falls JS-Consent serverseitig nicht rechtzeitig vorliegt — wird der Snapshot beim ersten Thankyou-Render aus den verfügbaren Request-Daten (Cookies) gebildet. Finalisierung in T2.5.

## 3. Order → kanonisches Event-Array (T2.2)

Klasse `Wp_Sdtrk_WC_Order_Mapper` (in `load_dependencies()` registrieren) übersetzt ein `WC_Order` in das von [`Wp_Sdtrk_Tracker_Event`](../public/class-wp-sdtrk-tracker-event.php) erwartete Array. Mehrere Felder sind **Listen** (Getter nutzt `grabFirstValue`):

```php
[
  'eventName'  => ['purchase'],                 // → parseEventName → 'purchase'
  'value'      => [ $order->get_total() ],      // → getEventValue (float)
  'orderId'    => [ (string) $order->get_id() ],// → getTransactionId + getEventId (Dedup!)
  'prodId'     => [ /* erstes/ggf. mehrere Produkt-IDs */ ],
  'prodName'   => [ /* erster Produktname */ ],
  'userEmail'     => [ $order->get_billing_email() ],
  'userFirstName' => [ $order->get_billing_first_name() ],
  'userLastName'  => [ $order->get_billing_last_name() ],
  'eventSource'         => [ /* Order-Received-URL */ ],
  'eventSourceAdress'   => $order->get_customer_ip_address(),  // einzelwertig (setAndFilled)
  'eventSourceAgent'    => $order->get_customer_user_agent(),
  'utm'        => [ /* aus Order-Meta, falls erfasst */ ],
  'currency'   => $order->get_currency(),        // s. u. Währungs-Hinweis
]
```

**Mehrere Produkte:** `getProductId/Name` liefern nur einen Wert. Für die plattformspezifische `contents[]`/`items[]`-Liste (mehrere Positionen) liefert der Mapper zusätzlich eine **strukturierte Positionsliste** (`line_items`: id, name, qty, price), die in T2.5 in die Plattform-Payloads einfließt. Das Basis-Event bleibt mit dem Event-Modell kompatibel (erstes Produkt), die Mehr-Produkt-Daten kommen über den `$data`-Parameter von `fireTracking_Server`.

**Währung:** Der Meta-Tracker hardcodet aktuell `"EUR"` ([class-wp-sdtrk-tracker-meta.php:149](../public/class-wp-sdtrk-tracker-meta.php#L149)). Für WC muss die Order-Währung (`$order->get_currency()`) verwendet werden — in T2.5 als bekannter Anpassungspunkt behandeln (Spec-Befund prüfen).

## 4. Server-Feuerung (T2.5)

Pro aktiver Plattform (`meta`/`ga`/`tt`):

```php
$event   = new Wp_Sdtrk_Tracker_Event( $mapper->toEventArray($order) );
$tracker = new Wp_Sdtrk_Tracker_Meta();            // bzw. _Ga / _Tt
$tracker->fireTracking_Server($event, 'Event', $serverData);  // Handler 'Event' → fireTracking_Server_Event
```

- Der Tracker self-gated bereits über `*_trk_server` (`trackingEnabled_Server`) — kein doppeltes Aktiv-Gate nötig.
- `$serverData` enthält `fbp`/`fbc` (Meta) bzw. `ttp`/`ttc` (TikTok) aus `_wp_sdtrk_ids`.
- **Consent-Gate** liegt **vor** dem Tracker-Aufruf: nur feuern, wenn `_wp_sdtrk_consent[service] === true` oder Bypass.
- **Idempotenz:** nach Erfolg `_wp_sdtrk_server_sent_{platform}` setzen; bei gesetztem Flag überspringen.
- **Dedup:** `event_id` = Order-ID (vom Event-Modell), identisch zum Browser-Event.

## 5. Aktivierung / Sichtbarkeit (T2.1)

- Gesamte WC-Integration nur aktiv, wenn `class_exists('WooCommerce')` **und** Redux-Switch `wc_integration` an.
- Redux-Sektion/Switch nur sichtbar, wenn WooCommerce aktiv (Registrierung gated über `class_exists('WooCommerce')` in [admin/class-wp-sdtrk-admin.php](../admin/class-wp-sdtrk-admin.php)).
- Hooks aus §1 nur registrieren, wenn Integration aktiv.

## 6. Risiken / Klärungen für Folge-Tasks

- **Consent-Snapshot-Timing** (§2): JS-AJAX vs. serverseitige Cookie-Auswertung — endgültig in T2.5.
- **Mehrere Produkte** (§3): Positionsliste über `$data`, nicht über das einwertige Event-Modell.
- **Währung** (§3): WC-Order-Währung statt hartem `EUR`.
- **Kunde erreicht Thankyou nie:** fail-closed (kein Server-Call ohne Consent-Snapshot), außer Bypass.
- **Verifikation:** WooCommerce ist im Dev-Site installiert (Testprodukt vorhanden) → manuelle Verifikation über Testbestellung + `debug.log` + Events Manager. Reine Logik (`Wp_Sdtrk_WC_Order_Mapper`) wird per eigenständigem PHP-Test abgesichert.
