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
- **Token:** Option `wp_sdtrk_feed_token`, bei Bedarf via `wp_generate_password(32, false)` erzeugt. Die fertige Feed-URL inkl. Token zeigt ein Info-Feld in der WooCommerce-Redux-Sektion.

## Generierung

| Methode | Aufgabe |
|---------|---------|
| `collect()` | veröffentlichte WC-Produkte + Variationen → Rohdaten-Zeilen |
| `feed_items($rows)` | Normalisierung (rein): `id` (SKU oder ID), Verfügbarkeit, Preis `"<Betrag> <Währung>"`, `condition=new`, `item_group_id` bei Variationen |
| `render_xml($items, $channel)` | RSS-2.0-/`g:`-Dokument (rein) |
| `generate()` | `render_xml(feed_items(collect()))` |

**Feld-Mapping (`g:`):** `g:id`, `title`, `description` (Tags entfernt), `link`, `g:image_link`, `g:availability` (`in_stock`/`out_of_stock`), `g:price`, `g:condition`, `g:brand`, `g:item_group_id` (Variationen). Die optionalen Felder `g:image_link`, `g:price`, `g:brand` und `g:item_group_id` werden bei leerem Wert **ganz weggelassen** — ein Produkt ohne Preis erzeugt also kein (fehlerhaftes) `<g:price>EUR</g:price>`, sondern gar kein Preis-Element.

**Währung:** direkt aus WooCommerce (`get_woocommerce_currency()`) — nicht von der `EUR`-Verdrahtung der Tracker betroffen.

**XML-Escaping (`esc()`):** Jeder ausgegebene Wert wird über `esc()` geschrieben. Diese Methode entfernt zunächst in XML 1.0 unzulässige C0-Steuerzeichen (alle außer Tab/LF/CR; byteweise, da alle `< 0x80`) und escaped dann mit `htmlspecialchars(ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE)`. `ENT_SUBSTITUTE` ersetzt ungültiges UTF-8 durch `U+FFFD`, statt dass `htmlspecialchars()` einen leeren String liefert und das Feld stillschweigend verschluckt. So bleibt der Feed auch bei Steuerzeichen oder defektem UTF-8 in Produktdaten wohlgeformt.

## Caching & Cron

- Der Cron-Hook `wp_sdtrk_cron_generate_feed` (täglich, [Lebenszyklus › Cron](../01-architecture/lifecycle.md)) ruft `cron_regenerate()` → `regenerate_cache()` und schreibt das XML in die Option `wp_sdtrk_feed_cache`.
- Der Endpoint liefert den Cache (`get_cached()`); fehlt der Cache, wird live generiert.

## Einschränkungen

- Sehr große Kataloge: `collect()` lädt alle Produkte ohne Batching.
- Produkte ohne Bild/Preis werden mit-exportiert (kein Filter); das jeweilige `g:`-Element fehlt dann, was das Merchant Center als fehlendes Pflichtfeld beanstanden kann.
