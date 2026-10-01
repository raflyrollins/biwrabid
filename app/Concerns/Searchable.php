<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Support\RawExpression;
use App\Support\SearchConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Support\Collection;

/**
 * Database full-text search for PostgreSQL.
 *
 * The `search` scope narrows to rows matching the term and orders them by
 * relevance. It deliberately rebuilds the exact `ts_rank` argument that
 * Laravel's `whereFullText()` compiles, because PostgreSQL matches a GIN
 * expression index byte for byte: any hand-written variant silently loses the
 * index and falls back to a sequential scan.
 *
 * See RULES.md — "The full-text index expression must match the query exactly".
 *
 * @see PostgresGrammar::whereFulltext()
 */
trait Searchable
{
    /**
     * Filter to rows matching `$term` and order them by relevance.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $columns = SearchConfig::columnsFor($this->getTable());

        if ($columns === []) {
            return $query;
        }

        $language = SearchConfig::language();
        $vector = $this->fullTextVector($columns, $language);

        $query->getQuery()->addBinding($term, 'order');

        return $query
            ->whereFullText($columns, $term, ['language' => $language])
            ->orderBy(
                new RawExpression("ts_rank({$vector}, plainto_tsquery('{$language}', ?))"),
                'desc',
            );
    }

    /**
     * Rebuild the vector expression Laravel uses elsewhere, from the same
     * column list and language, so it matches the full-text index.
     *
     * @param  array<int, string>  $columns
     */
    protected function fullTextVector(array $columns, string $language): string
    {
        $grammar = $this->getConnection()->getQueryGrammar();

        return Collection::make($columns)
            ->map(fn (string $column): string => sprintf(
                "to_tsvector('%s', %s)",
                $language,
                $grammar->wrap($column),
            ))
            ->implode(' || ');
    }
}
