<?php

namespace App\Support\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The filters every admin list shares: a search term, a date range and a sort, read
 * from the query string and checked against what the page allows, so a list, its
 * active-filter chips and its CSV export all apply the same thing.
 *
 * Sorting only ever uses a column named in the page's own whitelist; a request for
 * anything else falls back to the default, so user input never reaches ORDER BY.
 */
final class ListFilters
{
    /**
     * @param  array<string,string>  $sorts  query key => column
     */
    private function __construct(
        public readonly string $q,
        public readonly ?Carbon $from,
        public readonly ?Carbon $to,
        public readonly string $sort,
        public readonly string $dir,
        private readonly array $sorts,
    ) {}

    /** @param array<string,string> $sorts query key => column; the first is the default */
    public static function from(Request $request, array $sorts, string $defaultDir = 'desc'): self
    {
        $sort = (string) $request->query('sort', '');
        if (! array_key_exists($sort, $sorts)) {
            $sort = (string) array_key_first($sorts);
        }
        $dir = in_array($request->query('dir'), ['asc', 'desc'], true) ? (string) $request->query('dir') : $defaultDir;

        return new self(
            mb_substr(trim((string) $request->query('q', '')), 0, 120),
            self::date($request->query('from')),
            self::date($request->query('to'))?->endOfDay(),
            $sort,
            $dir,
            $sorts,
        );
    }

    private static function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Case-insensitive LIKE across the given columns, with % and _ taken literally. */
    public function search(Builder $query, array $columns): Builder
    {
        if ($this->q === '') {
            return $query;
        }
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($this->q)).'%';

        return $query->where(function ($w) use ($columns, $like) {
            foreach ($columns as $column) {
                $w->orWhereRaw('LOWER('.$column.') LIKE ? ESCAPE ?', [$like, '\\']);
            }
        });
    }

    public function dateRange(Builder $query, string $column): Builder
    {
        return $query
            ->when($this->from, fn ($q) => $q->where($column, '>=', $this->from))
            ->when($this->to, fn ($q) => $q->where($column, '<=', $this->to));
    }

    public function order(Builder $query): Builder
    {
        // The primary key breaks ties, so a page boundary never repeats or skips a row.
        $query->orderBy($this->sorts[$this->sort], $this->dir);

        return $this->sorts[$this->sort] === 'id' ? $query : $query->orderBy($query->getModel()->getQualifiedKeyName(), $this->dir);
    }

    public function active(): bool
    {
        return $this->q !== '' || $this->from !== null || $this->to !== null;
    }
}
