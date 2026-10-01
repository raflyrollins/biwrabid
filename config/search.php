<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Full-text Search Language
    |--------------------------------------------------------------------------
    |
    | The PostgreSQL text search configuration used for every full-text index
    | and query. It must be a real text search configuration installed on the
    | server (see `pg_ts_config`), otherwise PostgreSQL rejects the generated
    | expression loudly. `indonesian` is present on a stock PostgreSQL build.
    |
    */

    'language' => env('SEARCH_LANGUAGE', 'indonesian'),

    /*
    |--------------------------------------------------------------------------
    | Searchable Columns per Table
    |--------------------------------------------------------------------------
    |
    | The single declaration of which columns are searchable. Both the
    | migration that builds the full-text index and the `Searchable` scope
    | read from here, so the index expression and the query expression can
    | never drift apart. Keyed by table name.
    |
    */

    'columns' => [
        'auctions' => ['title', 'description'],
    ],
];
