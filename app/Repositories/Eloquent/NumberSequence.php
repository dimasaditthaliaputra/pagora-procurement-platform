<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Next yearly document number in the `PREFIX-YYYY-0001` style used by PO numbers.
 * Callers run inside a transaction holding a row lock; the unique index is the final guard.
 */
final class NumberSequence
{
    /**
     * @param  Builder<Model>  $query
     */
    public static function next(Builder $query, string $column, string $prefix): string
    {
        $latest = $query->where($column, 'like', $prefix.'%')->orderByDesc($column)->value($column);
        $lastSequence = $latest !== null ? (int) substr((string) $latest, strlen($prefix)) : 0;

        return sprintf('%s%04d', $prefix, $lastSequence + 1);
    }
}
