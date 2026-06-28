# 07 — Server-Purchase (Order-Status)

## Consent-Snapshot (Persistenz auf der Order)

Da der Server-Hook keinen Browser-Kontext hat, persistiert [public/js/wp-sdtrk-wc.js](../../public/js/wp-sdtrk-wc.js) auf der Order-Received-Seite einen Snapshot per AJAX (`action=wp_sdtrk_wc_persist`, Nonce `security_wp-sdtrk`). `Wp_Sdtrk_WC_Integration::handle_persist_ajax()` validiert Nonce **und** Order-Key (`hash_equals` gegen `$order->get_order_key()`) und speichert:

| Order-Meta | Inhalt |
|------------|--------|
| `_wp_sdtrk_consent` | `['meta' => bool, 'ga' => bool, 'tt' => bool]` — Server-Consent je Plattform (browser-seitig als `catcher.isEnabled('s')` ermittelt) |
| `_wp_sdtrk_ids` | Identifier genau wie vom Browser verwendet: `fbp`, `fbc`, `cid`, `ttp`, `ttc`, `hash` |

Die Identifier werden 1:1 vom Browser übernommen, damit Server- und Browser-Event identische `event_id`s bilden.

## Feuern auf Order-Status

`on_order_paid($order_id)` ist an `woocommerce_order_status_processing` **und** `…_completed` gehängt. Beide Übergänge können denselben Auftrag betreffen (Sofort- vs. asynchrone Zahlung); pro Plattform wird daher höchstens **einmal** gesendet.

Pro Plattform (`meta`/`ga`/`tt`):

1. **Idempotenz:** Order-Meta `_wp_sdtrk_server_sent_{platform}` gesetzt → überspringen.
2. **Consent-Gate** (reine Logik `should_fire_server($consented, $bypass, $already_sent)`):
   - bereits gesendet → `false`;
   - sonst `true`, wenn Bypass (`_wp_sdtrk_bypass`) **oder** Consent für die Plattform erteilt;
   - **fail-closed:** ohne Consent-Snapshot und ohne Bypass kein Server-Call.
3. **Feuern:** `new Wp_Sdtrk_Tracker_{Platform}()` → `fireTracking_Server($event, 'Event', $data)`. Das Event kommt aus `Wp_Sdtrk_WC_Order_Mapper::toEventArray()`. `$data` enthält die plattformspezifischen Identifier aus `_wp_sdtrk_ids` (Meta: `fbp`/`fbc`, GA: `cid`, TikTok: `ttp`/`ttc`/`hash`).
4. **Idempotenz-Flag** `_wp_sdtrk_server_sent_{platform}` setzen.

Die einzelnen Tracker self-gaten zusätzlich über ihre `*_trk_server`-Option.

## Deduplizierung

Gemeinsame `event_id` = Order-ID:

| Plattform | Browser | Server | Mechanik |
|-----------|---------|--------|----------|
| Meta | `eventID = Order-ID` | `event_id = getEventId() = Order-ID` | identische `event_id` |
| GA4 | `transaction_id = Order-ID` | `transaction_id = getEventId() = Order-ID` | GA4 dedupliziert Purchase per `transaction_id` |
| TikTok | `event_id = "<Order-ID>_<hashId>"` | `event_id = "<Order-ID>_<hash>"` | identischer `hash` aus dem Snapshot |

## Bekannte Einschränkungen

- **Währung:** Die Server-Tracker (und der Meta-Browser-Pfad) hardcoden `EUR`. Bei abweichender Shop-Währung stimmt die gemeldete Währung nicht. Siehe [99 Befunde](../99-findings.md).
- **Kein Thankyou-Besuch:** Ohne Consent-Snapshot feuert der Server fail-closed nicht (außer Bypass).
