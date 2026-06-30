# 07 — Produkt-Feed

Optionaler RSS-2.0-Produkt-Feed (Google-`g:`-Namespace) für Google Merchant Center und Meta Commerce Manager. Klasse `Wp_Sdtrk_WC_Feed` ([public/class-wp-sdtrk-wc-feed.php](../../public/class-wp-sdtrk-wc-feed.php)).

## Aktivierung

Verfügbar nur, wenn `Wp_Sdtrk_WC_Feed::is_enabled()`: WooCommerce-Integration aktiv (`is_active()`) **und** Redux-Schalter `wc_feed_enabled` an (WooCommerce-Sektion).

## Endpoint & Zugriffsschutz

- **Query-Var** (kein Rewrite/Flush): `…/?wp_sdtrk_feed=1&token=<TOKEN>`.
- Hook `template_redirect` → `handle_feed_request()`:
  - nicht aktiv → `404`;
  - falscher/fehlender Token (`hash_equals`) → `403`;
  - sonst `Content-Type: application/xml`, Ausgabe des gecachten Feeds, `exit`.
- **Token:** Option `wp_sdtrk_feed_token`, bei Bedarf via `wp_generate_password(32, false)` erzeugt (lazy, `get_token()`). Die fertige Feed-URL inkl. Token zeigt ein Info-Feld in der WooCommerce-Redux-Sektion.
- **Rotation:** Ein Button „Regenerate feed token" im selben Info-Feld ruft per Admin-AJAX (`func=regenerate_feed_token`, Nonce `security_wp-sdtrk`) `Wp_Sdtrk_WC_Feed::rotate_token()` (`wp_generate_password(32, false)` → `update_option`) und ersetzt die angezeigte URL. Die alte URL wird sofort ungültig (`403`) und muss überall neu hinterlegt werden. Dass das Token als `?token=`-Query-Parameter übertragen wird, ist von Google/Meta vorgegeben (nur eine URL) — die Feed-URL ist daher als Geheimnis zu behandeln.

## Generierung

| Methode | Aufgabe |
|---------|---------|
| `collect()` | veröffentlichte WC-Produkte + Variationen → Rohdaten-Zeilen, **abzüglich der ausgeschlossenen Produkte** (`exclude` an `wc_get_products`) |
| `feed_items($rows)` | Normalisierung (rein): `id` (SKU oder ID), Verfügbarkeit, Preis `"<Betrag> <Währung>"`, `condition=new`, `item_group_id` bei Variationen |
| `render_xml($items, $channel)` | RSS-2.0-/`g:`-Dokument (rein) |
| `generate()` | `render_xml(feed_items(collect()))` |

## Ausschluss-Liste (welche Produkte im Feed sind)

Per Default sind **alle veröffentlichten** Produkte im Feed; gesteuert wird ausschließlich, was **ausgeschlossen** wird. Verwaltet wird die Liste über die [Feed-Verwaltungsseite](feed-management.md).

- **Speicher:** eigenständige Option `wp_sdtrk_feed_excluded` (`autoload = false`, **nicht** Redux), ein Array der ausgeschlossenen Produkt-IDs. Kein Eintrag = enthalten, sodass neu veröffentlichte Produkte automatisch im Feed sind.
- **Helfer auf `Wp_Sdtrk_WC_Feed`:** `get_excluded_ids()` (liest + sanitisiert: `intval`, positive, dedupe; toleriert fehlenden/korrupten Wert → `[]`), `set_excluded_ids($ids)` (persistiert sanitisiert **und** invalidiert den Feed-Cache, s. u.), `is_excluded($id)`.
- **Filterung:** `collect()` reicht `get_excluded_ids()` als `exclude`-Argument an `wc_get_products()`. Da Variationen nur über die `get_children()`-Schleife des Elternprodukts gesammelt werden, entfernt der Ausschluss eines variablen Elternprodukts **transitiv** auch seine Variationen.
- **Granularität:** Ausschluss greift auf **Elternebene**; einzelne Variationen können nicht separat ausgeschlossen werden.

**Feld-Mapping (`g:`):** `g:id`, `title`, `description` (Tags entfernt), `link`, `g:image_link`, `g:availability` (`in_stock`/`out_of_stock`), `g:price`, `g:condition`, `g:brand`, `g:item_group_id` (Variationen). Die optionalen Felder `g:image_link`, `g:price`, `g:brand` und `g:item_group_id` werden bei leerem Wert **ganz weggelassen** — ein Produkt ohne Preis erzeugt also kein (fehlerhaftes) `<g:price>EUR</g:price>`, sondern gar kein Preis-Element.

**Währung:** direkt aus WooCommerce (`get_woocommerce_currency()`) — nicht von der `EUR`-Verdrahtung der Tracker betroffen.

**XML-Escaping (`esc()`):** Jeder ausgegebene Wert wird über `esc()` geschrieben. Diese Methode entfernt zunächst in XML 1.0 unzulässige C0-Steuerzeichen (alle außer Tab/LF/CR; byteweise, da alle `< 0x80`) und escaped dann mit `htmlspecialchars(ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE)`. `ENT_SUBSTITUTE` ersetzt ungültiges UTF-8 durch `U+FFFD`, statt dass `htmlspecialchars()` einen leeren String liefert und das Feld stillschweigend verschluckt. So bleibt der Feed auch bei Steuerzeichen oder defektem UTF-8 in Produktdaten wohlgeformt.

## Caching & Cron

- Der Cron-Hook `wp_sdtrk_cron_generate_feed` (täglich, [Lebenszyklus › Cron](../01-architecture/lifecycle.md)) ruft `cron_regenerate()` → `regenerate_cache()` und schreibt das XML in die Option `wp_sdtrk_feed_cache`.
- Der Endpoint liefert den Cache über `get_or_build_cached()`:
  - **Cache vorhanden:** wird direkt ausgegeben.
  - **Kalter Cache** (Cron noch nicht gelaufen / Cache geleert): Es baut **nur ein** Request live auf, abgesichert durch einen kurzlebigen Transient-Lock (`wp_sdtrk_feed_lock`, TTL 300 s). Parallele Requests bekommen währenddessen `503` mit `Retry-After: 120` (kein paralleler Voll-Aufbau → Stampede-Schutz).
- `get_cached()` ist ein **reiner** Getter (liefert Cache oder `''`, baut nie) — genutzt für Cron/Tests.
- **Invalidierung bei Ausschluss-Änderung:** `set_excluded_ids()` löscht den Cache (`delete_option(wp_sdtrk_feed_cache)`). Der nächste Abruf läuft damit über den kalten-Cache-Pfad (`get_or_build_cached()`) und baut den Feed unter dem Stampede-Lock neu — die Änderung wird ohne Warten auf den Cron sichtbar.

## Einschränkungen

- Sehr große Kataloge: `collect()` lädt alle Produkte ohne Batching/Pagination. Der Stampede-Lock verhindert parallele Voll-Aufbauten, nicht die Kosten eines einzelnen Aufbaus.
- Produkte ohne Bild/Preis werden mit-exportiert (kein Filter); das jeweilige `g:`-Element fehlt dann, was das Merchant Center als fehlendes Pflichtfeld beanstanden kann.
