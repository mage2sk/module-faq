<?php
declare(strict_types=1);

namespace Panth\Faq\Model\ResourceModel;

class StoreValueRow
{
    // Set on a model that took the store view from the storefront request
    // instead of an explicit store_scope_id. Saving such a model never writes
    // a store view row.
    public const IMPLICIT_SCOPE_KEY = '_panth_implicit_store_scope';

    // The scoped values a storefront load showed (store view value or default).
    public const LOADED_VALUES_KEY = '_panth_loaded_scoped_values';

    // Accepts the list form (['question', ...]) sent by the admin form and the
    // keyed form (['question' => 1, ...]) that a store-scoped load produces.
    // A keyed entry only counts while the field still holds the value that was
    // loaded as the default: code that changes the field after loading means
    // to override it.
    public static function useDefaultFields($raw, array $fields, array $loadedDefaults = [], array $data = []): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $list = [];
        foreach ($raw as $key => $value) {
            $field = is_string($key) ? $key : (string)$value;
            if ($value === '0' || $value === 0 || $value === false || $value === null || $value === '') {
                continue;
            }
            if (!in_array($field, $fields, true)) {
                continue;
            }
            if (is_string($key) && self::changedSinceLoad($field, $loadedDefaults, $data)) {
                continue;
            }
            $list[] = $field;
        }
        return array_values(array_unique($list));
    }

    // One value per scoped field, null meaning "inherit the base row". A value
    // equal to the base value is stored as null, so later base edits reach
    // the store view.
    public static function build(array $fields, array $data, array $defaults, array $useDefault): array
    {
        $row = [];
        foreach ($fields as $field) {
            $value = $data[$field] ?? null;
            if (in_array($field, $useDefault, true) || $value === '' || $value === null) {
                $row[$field] = null;
                continue;
            }
            $row[$field] = self::sameAsDefault($value, $defaults, $field) ? null : $value;
        }
        return $row;
    }

    public static function inheritsEverything(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null) {
                return false;
            }
        }
        return true;
    }

    public static function isImplicit(array $data, int $storeId): bool
    {
        return $storeId > 0 && (int)($data[self::IMPLICIT_SCOPE_KEY] ?? 0) === $storeId;
    }

    // Values for the base row when a storefront-loaded model is saved: a field
    // changed in code keeps its new value, every other field keeps the default,
    // so store view text shown on the storefront is never copied into the base.
    public static function baseRowValues(array $fields, array $data, array $defaults): array
    {
        $loaded = (array)($data[self::LOADED_VALUES_KEY] ?? []);
        $values = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $defaults)) {
                continue;
            }
            $changed = array_key_exists($field, $loaded) && array_key_exists($field, $data)
                && (string)$data[$field] !== (string)$loaded[$field];
            $values[$field] = $changed ? $data[$field] : $defaults[$field];
        }
        return $values;
    }

    private static function changedSinceLoad(string $field, array $loadedDefaults, array $data): bool
    {
        if (!array_key_exists($field, $loadedDefaults)) {
            return false;
        }
        return (string)($data[$field] ?? '') !== (string)($loadedDefaults[$field] ?? '');
    }

    private static function sameAsDefault($value, array $defaults, string $field): bool
    {
        if (!array_key_exists($field, $defaults) || $defaults[$field] === null || is_array($value)) {
            return false;
        }
        return (string)$value === (string)$defaults[$field];
    }
}
