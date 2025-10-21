<?php

/**
 * Abstract base class for WordPress models.
 *
 * Provides generic CRUD operations (load, insert, update, delete)
 * via late static binding. Subclasses müssen nur Tabelle und Spalten
 * konfigurieren – alles andere passiert automatisch.
 */
abstract class WP_SDTRK_Model_Base
{
    /**
     * Primary key of this record (null if not persisted).
     *
     * @var int|null
     */
    protected ?int $id;

    /**
     * Full table name including WP prefix.
     *
     * @var string
     */
    protected string $table_name;

    /**
     * Child classes müssen hier ihren Tabellen-Namen (ohne Prefix) setzen.
     *
     * @var string
     */
    protected static string $table = '';

    /**
     * Mapping column => WPDB-Formatstring.
     * Beispiel: ['id'=>'%d','title'=>'%s','views'=>'%d']
     *
     * @var array<string,string>
     */
    protected static array $db_fields = [];

    /**
     * Liste der Spalten, die nie per save()/update() überschrieben werden.
     * Z.B. ['id','created_at']
     *
     * @var string[]
     */
    protected static array $guarded = ['id'];

    /** column => type, currently only 'datetime' supported */
    protected static array $casts = [];

    /**
     * Constructor.
     *
     * @param int|null $id  Wenn gegeben, existierender Datensatz-ID.
     */
    public function __construct(?int $id = null)
    {
        global $wpdb;
        if ('' === static::$table) {
            throw new \LogicException(
                __('Child class must set protected static $table', 'wp-sdtrk')
            );
        }
        $this->table_name = $wpdb->prefix . static::$table;
        $this->id         = $id;
    }

    /**
     * Populate this model instance from a raw database row and apply configured type casts.
     *
     * This method performs two main steps:
     * 1. **Hydration**: For each key/value in the provided `$row` array, if the model
     *    has a property with the same name, the value is assigned to that property.
     * 2. **Casting**: For each column listed in `static::$casts`, the raw value is converted
     *    to its target type. Currently, only the "datetime" cast is supported: any non-empty
     *    string in MySQL DATETIME format will be transformed into a `\DateTime` object.
     *
     * Example:
     * ```php
     * // Suppose $row was fetched like this:
     * $row = [
     *     'id'          => 42,
     *     'user_id'     => 7,
     *     'start_date'  => '2025-05-13 09:00:00',
     *     'expiry_date' => null,
     *     'status'      => 'active',
     *     'note'        => 'Sample record',
     * ];
     *
     * // And the subclass defines:
     * // protected static array $casts = [
     * //     'start_date'  => 'datetime',
     * //     'expiry_date' => 'datetime',
     * // ];
     *
     * $instance = new static();
     * $instance->hydrateRow($row);
     *
     * // After hydration and casting:
     * // $instance->id          === 42
     * // $instance->user_id     === 7
     * // $instance->start_date  instanceof \DateTime representing 2025-05-13 09:00:00
     * // $instance->expiry_date === null
     * // $instance->status      === 'active'
     * // $instance->note        === 'Sample record'
     * ```
     *
     * @param array<string,mixed> $row  Associative array as returned by `$wpdb->get_row(..., ARRAY_A)`.
     * @return void
     */
    protected function hydrateRow(array $row): void
    {
        foreach (static::$db_fields as $col => $_) {
            if (! array_key_exists($col, $row) || ! property_exists($this, $col)) {
                continue;
            }

            $raw = $row[$col];
            $cast = static::$casts[$col] ?? null;

            // 1) Casten
            if ($cast === 'datetime' && ! empty($raw)) {
                $value = new \DateTime((string) $raw);
            } elseif ($cast === 'json' && is_string($raw)) {
                $value = json_decode($raw, true);
            } elseif ($cast === 'bool') {
                $value = (bool) $raw;
            } else {
                // kein Cast oder unbekannt: rohen Wert übernehmen
                $value = $raw;
            }

            // 2) In die Eigenschaft schreiben (typisiert korrekt)
            $this->$col = $value;
        }
    }

    /**
     * Factory: create an instance from a raw DB row.
     *
     * @param  array<string,mixed> $row  as returned by $wpdb->get_row(..., ARRAY_A)
     * @return static
     */
    public static function load_by_row(array $row): static
    {
        $instance = new static();
        $instance->hydrateRow($row);
        return $instance;
    }

    /**
     * Load a record by its primary key.
     *
     * @param  int          $id  Primary key to load.
     * @return static            Instanz mit gefüllten Properties.
     * @throws RuntimeException  Wenn kein Datensatz gefunden.
     */
    public static function load_by_id(int $id): static
    {
        global $wpdb;
        $instance = new static($id);
        $sql      = $wpdb->prepare(
            "SELECT * FROM {$instance->table_name} WHERE id = %d",
            $id
        );
        $row = $wpdb->get_row($sql, ARRAY_A);

        if (! $row) {
            throw new \RuntimeException(
                sprintf(__('Record with ID %d not found in %s', 'wp-sdtrk'), $id, $instance->table_name)
            );
        }

        // Einziger Aufruf der zentralen Hydration/Casting-Logik:
        $instance->hydrateRow($row);

        return $instance;
    }

    /**
     * Get the WPDB-format mapping for columns.
     *
     * @return array<string,string>  e.g. ['id'=>'%d','name'=>'%s', …]
     */
    public static function getDbFields(): array
    {
        return static::$db_fields;
    }

    /**
     * Insert a new record.
     *
     * @return void
     * @throws Exception On DB error.
     */
    protected function insert(): void
    {
        global $wpdb;
        [$data, $formats] = $this->buildPayload();

        $ok = $wpdb->insert($this->table_name, $data, $formats);
        if (false === $ok) {
            throw new \Exception(
                sprintf(__('DB insert error: %s', 'wp-sdtrk'), $wpdb->last_error)
            );
        }
        $this->id = (int) $wpdb->insert_id;
    }

    /**
     * Update an existing record.
     *
     * @return void
     * @throws LogicException If ID is null.
     * @throws Exception      On DB error.
     */
    protected function update(): void
    {
        if (null === $this->id) {
            throw new \LogicException(
                __('Cannot update without an ID', 'wp-sdtrk')
            );
        }

        global $wpdb;
        [$data, $formats] = $this->buildPayload();

        $ok = $wpdb->update(
            $this->table_name,
            $data,
            ['id' => $this->id],
            $formats,
            ['%d'] // Format für WHERE id = %d
        );
        if (false === $ok) {
            throw new \Exception(
                sprintf(__('DB update error: %s', 'wp-sdtrk'), $wpdb->last_error)
            );
        }
    }

    /**
     * Build payload, apply casts for insert/update.
     *
     * @return array{0: array<string,mixed>,1: string[]}
     */
    protected function buildPayload(): array
    {
        $data    = [];
        $formats = [];

        foreach (static::$db_fields as $col => $fmt) {
            if (in_array($col, static::$guarded, true)) {
                continue;
            }

            $value = $this->$col ?? null;

            // Cast back
            if (isset(static::$casts[$col])) {
                if (static::$casts[$col] === 'datetime' && $value instanceof \DateTime) {
                    $value = $value->format('Y-m-d H:i:s');
                }
                if (static::$casts[$col] === 'json' && is_array($value)) {
                    $value = json_encode($value);
                }
                if (isset(static::$casts[$col]) && static::$casts[$col] === 'bool') {
                    $value = $value ? 1 : 0;
                }
            }

            $data[$col]   = $value;
            $formats[]    = $fmt;
        }

        return [$data, $formats];
    }

    /**
     * Save this model: Insert if new, otherwise Update.
     *
     * @return void
     */
    public function save(): void
    {
        if ($this->id) {
            $this->update();
        } else {
            $this->insert();
        }
    }

    /**
     * Delete this record from the database.
     *
     * @return void
     * @throws Exception On DB error.
     */
    public function delete(): void
    {
        if (null === $this->id) {
            return;
        }

        global $wpdb;
        $ok = $wpdb->delete(
            $this->table_name,
            ['id' => $this->id],
            ['%d']
        );
        if (false === $ok) {
            throw new \Exception(
                sprintf(__("DB delete error: %s", 'wp-sdtrk'), $wpdb->last_error)
            );
        }
        $this->id = null;
    }

    /**
     * Get the primary key.
     *
     * @return int|null
     */
    public function get_id(): ?int
    {
        return $this->id;
    }

    /**
     * Get the full table name.
     *
     * @return string
     */
    public function get_table_name(): string
    {
        return $this->table_name;
    }

    /**
     * Normalize a “date” input into a DateTime or null.
     *
     * @param  \DateTime|int|string|null $input
     *   - null: bleibt null
     *   - DateTime: wird direkt zurückgegeben
     *   - int|numeric string: UNIX‐Timestamp
     *   - sonstiger string: MySQL-Datetime ("Y-m-d H:i:s")
     * @return \DateTime|null
     * @throws \InvalidArgumentException on invalid string
     */
    protected static function normalizeDateTime(\DateTime|int|string|null $input): ?\DateTime
    {
        return WP_SDTRK_Helper_Base::normalizeDateTime($input);
    }
}
