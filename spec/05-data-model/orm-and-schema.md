# 05 — Base-Model (ORM) & DB-Schema

## 1. `WP_SDTRK_Model_Base`

Datei: `includes/models/class-wp-sdtrk-model-base.php`. ActiveRecord-artig; jede Subklasse konfiguriert sich über statische Felder.

| Statisches Feld | Zweck |
|-----------------|-------|
| `$table` | Tabellenname ohne WP-Präfix (z. B. `sdtrk_linkedin`) |
| `$db_fields` | Spalten → Format-String (`'%d'` / `'%s'`) für `$wpdb->prepare()` |
| `$casts` | Typ-Casting beim Laden/Speichern (`datetime`, `json`, `bool`) |
| `$guarded` | nicht überschreibbare Spalten (Default: `id`) |

### CRUD- & Hilfsmethoden

| Methode | Zweck |
|---------|-------|
| `load_by_id(int $id): static` | Datensatz per PK laden |
| `load_by_row(array $row): static` | Objekt aus DB-Zeile bauen |
| `save(): void` | INSERT oder UPDATE (anhand `$id`) |
| `insert(): void` / `update(): void` | explizit |
| `delete(): void` | löschen |
| `hydrateRow(array $row): void` | Felder setzen + Casts anwenden |
| `buildPayload(): array` | Speicher-Payload (Reverse-Casting, z. B. `json_encode`) |

> **Casts-Beispiel:** `rules` ist in der DB ein LONGTEXT-JSON, im Objekt ein PHP-Array (`'rules' => 'json'`).

## 2. DB-Schema

Erzeugt in `Wp_Sdtrk_Activator::create_db_linkedin_mapping()` via `dbDelta()`.

### Tabelle `{prefix}sdtrk_linkedin`

| Spalte | Typ | Beschreibung |
|--------|-----|--------------|
| `id` | BIGINT UNSIGNED, PK, AUTO_INCREMENT | Primärschlüssel |
| `event` | VARCHAR(255) NOT NULL | Event-Name (Standard, dynamisch oder tag-basiert) |
| `convid` | VARCHAR(255) NOT NULL | LinkedIn Conversion-ID |
| `rules` | LONGTEXT NOT NULL | JSON-Array von `{key_name, value}`-Regeln |

**Constraint:** `UNIQUE KEY idx_event_convid (event, convid)` — kein doppeltes Mapping für dieselbe Event/ConvID-Kombination.

> Die Tabelle wird bei **Deaktivierung und Deinstallation nicht entfernt** ([01 › Lebenszyklus](../01-architecture/lifecycle.md), [99 Befunde](../99-findings.md)).
