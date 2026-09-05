<?php

namespace App\Models\Concerns;

trait NormalizesCatalogFields
{
    public function setAttribute($key, $value)
    {
        if (in_array($key, ['name', 'size', 'type_series', 'thickness', 'unit'], true)) {
            $value = preg_replace('/\s+/u', ' ', trim((string) $value));

            if ($key === 'unit') {
                $value = strtolower($value);
            }
        }

        return parent::setAttribute($key, $value);
    }
}
