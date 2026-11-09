<?php

declare(strict_types=1);

namespace CraftCms\Stripe\Support;

use CraftCms\Cms\Database\QueryParam;
use CraftCms\Cms\Support\Arr;
use DateTimeInterface;
use Illuminate\Support\Facades\Date;

/**
 * Helpers for query params on the columns generated from Stripe objects: JSON lists, such as a price’s
 * currencies, and Unix timestamps, such as a subscription’s current period end.
 *
 * @since 2.0.0
 */
final class QueryParams
{
    /**
     * Wraps each value of a query param in wildcards, so it matches a value anywhere in the column.
     *
     * Values that start with `:`, such as `:empty:`, and the `and`, `or` and `not` operators are left alone.
     */
    public static function contains(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::wrap($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        return array_map(
            fn (mixed $item) => is_string($item) && ! in_array(strtolower($item), ['and', 'or', 'not'], true)
                ? self::wrap($item)
                : $item,
            Arr::wrap($value),
        );
    }

    /**
     * Converts the dates in a date query param, such as `>= 2026-01-01`, to Unix timestamps.
     *
     * Stripe sends dates as Unix timestamps, which the generated columns hold as strings of the same length,
     * so they compare correctly as strings.
     */
    public static function timestamps(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $param = QueryParam::parse($value);
        $values = [$param->operator];

        foreach ($param->values as $item) {
            if (is_string($item) && str_starts_with($item, ':')) {
                $values[] = $item;

                continue;
            }

            $operator = '';

            if (is_string($item) && preg_match('/^(not\s+|!=|<=|>=|<|>|=)\s*(.+)$/i', $item, $match)) {
                [, $operator, $item] = $match;
            }

            $date = $item instanceof DateTimeInterface ? $item : Date::parse(is_numeric($item) ? '@'.$item : $item);
            $values[] = $operator.$date->getTimestamp();
        }

        return $values;
    }

    private static function wrap(string $value): string
    {
        return str_starts_with($value, ':') ? $value : "*$value*";
    }
}
