<?php

/**
 * @template TModel of WP_SDTRK_Model_Base
 */
abstract class WP_SDTRK_Helper_Base
{
    /**
     * Table name **without** WP prefix.
     *
     * @var string
     */
    protected static string $table = '';

    /**
     * Fully qualified model class this helper works with.
     *
     * @var class-string<WP_SDTRK_Model_Base>
     */
    protected static string $model_class = '';

    /**
     * Get the WPDB-prefixed table name.
     *
     * @return string
     */
    protected static function getTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . static::$table;
    }

    /**
     * Find multiple models by arbitrary WHERE-criteria.
     *
     * Supports:
     * - scalar value → `col = %format`
     * - array value  → `col IN (%format,…)`
     * - ORDER BY, LIMIT, OFFSET
     *
     * @param  array<string,mixed> $criteria  column => value or array of values
     * @param  array<string,string> $order    column => 'ASC'|'DESC'
     * @param  int|null            $limit
     * @param  int|null            $offset
     * @return WP_SDTRK_Model_Base[]            Array of model instances
     */
    public static function find(
        array $criteria,
        array $order = [],
        ?int  $limit = null,
        ?int  $offset = null
    ): array {
        global $wpdb;
        $table      = static::getTableName();
        $formatsMap = static::$model_class::getDbFields();

        $where   = [];
        $values  = [];

        foreach ($criteria as $col => $val) {
            if (! isset($formatsMap[$col])) {
                throw new \InvalidArgumentException(
                    sprintf(__("Unknown column %s", 'wp-sdtrk'), $col)
                );
            }
            $fmt = $formatsMap[$col];

            if (is_array($val) && count($val) > 0) {
                // IN (...)
                $ph = implode(',', array_fill(0, count($val), $fmt));
                $where[]  = "`{$col}` IN ({$ph})";
                $values   = array_merge($values, $val);
            } elseif (!is_array($val)) {
                // =
                $where[] = "`{$col}` = {$fmt}";
                $values[] = $val;
            }
        }

        $sql = "SELECT * FROM {$table}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        // ORDER BY
        if (!empty($order)) {
            $parts = [];
            foreach ($order as $col => $dir) {
                $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
                $parts[] = "`{$col}` {$dir}";
            }
            $sql .= ' ORDER BY ' . implode(', ', $parts);
        }

        // LIMIT & OFFSET
        if (null !== $limit) {
            $sql .= ' LIMIT ' . intval($limit);
        }
        if (null !== $offset) {
            $sql .= ' OFFSET ' . intval($offset);
        }

        $prepared = $values ? $wpdb->prepare($sql, ...$values) : $sql;
        $rows     = $wpdb->get_results($prepared, ARRAY_A) ?: [];

        return array_map(
            fn(array $row) => static::$model_class::load_by_row($row),
            $rows
        );
    }

    /**
     * Find exactly one model matching the criteria (or null).
     *
     * @param  array<string,mixed> $criteria
     * @return WP_SDTRK_Model_Base|null
     */
    public static function findOneBy(array $criteria): ?WP_SDTRK_Model_Base
    {
        $results = static::find($criteria, [], 1);
        return $results[0] ?? null;
    }

    /**
     * Fetch one record by primary key.
     *
     * @param  int         $id
     * @return TModel
     */
    public static function get_by_id(int $id): WP_SDTRK_Model_Base
    {
        /** @var TModel $instance */
        $instance = static::$model_class::load_by_id($id);
        return $instance;
    }

    /**
     * Fetch multiple records by their primary keys.
     *
     * @param  int[] $ids
     * @return TModel[]
     */
    public static function get_by_ids(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        return static::find(['id' => $ids]);
    }

    /**
     * Fetch all records, ordered by ID.
     *
     * @return TModel[]
     */
    public static function get_all(): array
    {
        return static::find([]);
    }

    public static function normalizeDateTime(\DateTime|int|string|null $input): ?\DateTime
    {
        if (null === $input) {
            return null;
        }
        if ($input instanceof \DateTime) {
            return $input;
        }
        // Timestamp?
        if (is_int($input) || (is_string($input) && ctype_digit($input))) {
            $dt = new \DateTime("@{$input}");
            $dt->setTimezone(new \DateTimeZone(wp_timezone_string()));
            return $dt;
        }
        // MySQL-Datetime
        try {
            return new \DateTime((string)$input, new \DateTimeZone(wp_timezone_string()));
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf(__("Failed to normalize to Datetime. Invalid datetime value given: %s", 'wp-sdtrk'), $input));
        }
    }
}
