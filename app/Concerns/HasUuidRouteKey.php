<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Gives a model an opaque `uuid` column that route model binding resolves,
 * while keeping the integer `id` as the real primary key.
 *
 * Using `HasUuids` on its own is wrong here: `HasUniqueStringIds::uniqueIds()`
 * falls back to `[$this->getKeyName()]`, which would write the generated UUID
 * into `id` and drop the integer key entirely.
 *
 * @see RULES.md — "UUID in URLs"
 */
trait HasUuidRouteKey
{
    use HasUuids;

    /**
     * The columns that receive a generated UUID.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * The column route model binding resolves against.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
