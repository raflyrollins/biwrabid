<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reads the full-text search declaration in `config/search.php`.
 *
 * Both the migration that builds a full-text index and the query scope that
 * uses it go through here, so the two can never drift apart.
 *
 * See RULES.md — "Index everything that joins or gets searched".
 */
final class SearchConfig
{
    /**
     * The searchable columns for a table.
     *
     * @return array<int, string>
     */
    public static function columnsFor(string $table): array
    {
        $columns = config("search.columns.{$table}");

        if (is_string($columns)) {
            return [$columns];
        }

        if (! is_array($columns)) {
            return [];
        }

        return array_values(array_filter($columns, 'is_string'));
    }

    /**
     * The PostgreSQL text search configuration to search with.
     */
    public static function language(): string
    {
        $language = config('search.language');

        return is_string($language) && $language !== '' ? $language : 'english';
    }
}
