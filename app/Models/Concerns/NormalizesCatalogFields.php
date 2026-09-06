<?php

namespace App\Models\Concerns;

trait NormalizesCatalogFields
{
    public static function normalizeCatalogField(string $key, mixed $value): mixed
    {
        if (! in_array($key, ['name', 'size', 'type_series', 'thickness', 'unit'], true)) {
            return $value;
        }

        if ($value === null && in_array($key, ['size', 'type_series', 'thickness'], true)) {
            return '';
        }

        if (! is_string($value)) {
            return $value;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($value));

        if (! is_string($normalized)) {
            return $value;
        }

        return $key === 'unit' ? strtolower($normalized) : $normalized;
    }

    public function setAttribute($key, $value)
    {
        return parent::setAttribute($key, self::normalizeCatalogField((string) $key, $value));
    }
}
