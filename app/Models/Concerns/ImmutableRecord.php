<?php

namespace App\Models\Concerns;

use LogicException;

trait ImmutableRecord
{
    protected static function bootImmutableRecord(): void
    {
        static::updating(function (): never {
            throw new LogicException('Historical records cannot be updated.');
        });

        static::deleting(function (): never {
            throw new LogicException('Historical records cannot be deleted.');
        });
    }
}
