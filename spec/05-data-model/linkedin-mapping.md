# 05 — LinkedIn Conversion-Mapping

Das LinkedIn-Insight-Tag kennt keine semantischen Event-Namen, sondern nur numerische **Conversion-IDs**. Das Mapping-Feature ordnet den internen Events (inkl. Signal-Events) gezielt LinkedIn-Conversion-IDs zu — optional gefiltert über Regeln.

## 1. Fachmodelle

### `WP_SDTRK_Model_Linkedin`

Datei: `includes/models/class-wp-sdtrk-model-linkedin.php`. Tabelle `sdtrk_linkedin`, `rules` als JSON-Cast.

Properties: `id`, `convid`, `event`, `rules` (Array).

| Setter | Validierung |
|--------|-------------|
| `set_conversion_id($convid)` | nicht leer |
| `set_event($event)` | gegen Default-/Dynamic-Events + Tag-Pattern, sonst `InvalidArgumentException` |
| `set_rules($rules_json)` | gültiges JSON |

| Getter | Rückgabe |
|--------|----------|
| `get_conversion_id()` / `get_event()` | string |
| `get_event_label()` | übersetzter Anzeigename |
| `get_scroll_depth()` / `get_time_seconds()` | int (`-1` wenn nicht zutreffend) |
| `get_tag_name()` | string (`''` wenn nicht tag-basiert) |
| `get_rules()` | `WP_SDTRK_Model_Linkedin_Rule[]` |

Event-Typ-Prüfer: `is_scroll_event()`, `is_time_event()`, `is_button_click_event()`, `is_element_visibility_event()`, `is_valid_event()`.

### `WP_SDTRK_Model_Linkedin_Rule`

Value-Object: `key_name` (z. B. `prodid`, `prodname`) + `value`. Getter `get_key_name()`, `get_value()`, `get_label()`.

## 2. Event-Typen & Muster

| Typ | Beispiel / Muster |
|-----|-------------------|
| Standard-Event | `purchase`, `view_item`, `generate_lead`, … |
| Scroll | `Scroll_%s` (z. B. `Scroll_50`) |
| Time | `Time_%s` (z. B. `Time_10`) |
| Button-Click | `button_click_%s` (z. B. `button_click_newsletter`) |
| Element-Visibility | `element_visible_%s` |

Quelle der gültigen Events: `WP_SDTRK_Helper_Event::get_default_events()` + `get_dynamic_events()`.

## 3. Regeln (Rules)

- Liste von `{key_name, value}`-Bedingungen, **AND-verknüpft** — alle müssen erfüllt sein, damit das Mapping triggert.
- Leerer `value` matcht jeden Wert.
- Verfügbare Keys: `prodid`, `prodname`.
- **Alternative für tag-basierte Events:** statt Rules ein `element_tag` (Button-/Element-Tag).

## 4. CRUD — `WP_SDTRK_Helper_Linkedin`

Datei: `includes/helpers/class-wp-sdtrk-helper-linkedin.php` (erbt von `WP_SDTRK_Helper_Base`).

| Methode | Zweck |
|---------|-------|
| `get_all_mappings()` | alle Mappings (`ORDER BY id ASC`) |
| `get_by_event_and_convid($event, $convid)` | Eindeutigkeits-Lookup |
| `create($convid, $event, $rules)` | INSERT (mit Unique-Check, sonst Exception) |
| `update($id, $event, $convid, $rules)` | UPDATE |
| `delete($id)` | DELETE |

Plus geerbte Methoden (`get_by_id`, `find`, …).

## 5. Admin-UI-Fluss

Template `templates/wp-sdtrk-admin-map-linkedin.php`, JS `admin/js/wp-sdtrk-admin-map-linkedin.js`.

```
LinkedIn-Tab ──Button──▶ Seite wp_sdtrk_admin_map_linkedin
  ├─ Tabelle bestehender Mappings (Event | ConvID | Rules | Aktionen)
  ├─ Formular „Neues Mapping"
  │    ├─ Event-Dropdown ──change──▶ toggleSections()  (Rules vs. Tag-Feld)
  │    ├─ Rules dynamisch: Add/Remove, Dropdown zeigt nur ungenutzte Keys
  │    └─ Submit POST ─▶ create_linkedin_mapping (Form-Handler)
  ├─ Edit ─AJAX─▶ get_linkedin_mapping ─▶ Modal füllen ─Submit─▶ update_linkedin_mapping
  └─ Delete ─Bestätigung─▶ AJAX delete_linkedin_mapping
```

JS-Hilfsfunktionen: `isTagBasedEvent`, `toggleSections`, `getUsedRules`, `getAvailableRules`, `updateRuleDropdowns`, `handleAddRule`, `handleRemoveRule`.

## 6. Auslieferung an den Browser

Beim Frontend-Enqueue werden die Mappings via `wp_localize_script` als `wp_sdtrk_lin` ausgespielt:

```jsonc
wp_sdtrk_lin = {
  pid: "<partner id>",
  map_ev:  [{ eventName, convId, rules }],
  map_btn: [{ btnTag, convId }],
  map_iv:  [{ ivTag, convId }],
  pattern_scroll_event: "Scroll__%s",
  pattern_time_event:   "Time__%s",
  dbg: false
}
```

Der Browser-Catcher (`Wp_Sdtrk_Catcher_Lin`) wertet diese Mappings aus und feuert `lintrk('track', { conversion_id })` — siehe [03 › Catcher](../03-browser-tracking/catchers.md#4-linkedin-catcher-conversion-mapping).
