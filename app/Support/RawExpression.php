<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

/**
 * A raw SQL fragment assembled at runtime.
 *
 * `Illuminate\Database\Query\Expression` only accepts a `literal-string`,
 * which a fragment built from `config/search.php` can never be. This adapter
 * implements the same contract with a plain string, so generated SQL — never
 * user input, which is always bound — can be handed to the query builder
 * without casting or suppressing the type checker.
 */
final class RawExpression implements Expression
{
    public function __construct(private readonly string $value) {}

    public function getValue(Grammar $grammar): string
    {
        return $this->value;
    }
}
