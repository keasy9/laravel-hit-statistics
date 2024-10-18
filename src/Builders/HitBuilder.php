<?php

namespace Keasy9\HitStatistics\Builders;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Keasy9\HitStatistics\Enums\PeriodEnum;

/**
 * @method static days()
 * @method static weeks()
 * @method static months()
 * @method static quarters()
 * @method static halfYears()
 * @method static years()
 * @method static countries()
 * @method static cities()
 */
class HitBuilder extends Builder
{
    const DATE_SEPARATOR = ' - ';

    protected array $aggregations = [];

    protected array $columnsMap = [
        'countries' => 'country',
        'cities'    => 'city',
    ];

    protected array $masks = [];

    public function __construct(QueryBuilder $query)
    {
        $this->masks = config('hits.masked') ?? [];
        parent::__construct($query);
    }

    public function __call($method, $parameters)
    {
        $result = $this->tryPeriod($method)
            || $this->tryColumn($method)
            || $this->tryMask($method);

        if ($result) {
            return $this;
        }

        return parent::__call($method, $parameters);
    }

    public function forPeriod(CarbonPeriod $period = null): static
    {
        $period ??= CarbonPeriod::create('now', CarbonInterval::day(), Carbon::now()->subMonth());

        return $this->whereBetween('visited_at', [
            $period->getEndDate()->startOfDay(),
            $period->getStartDate()->endOfDay(),
        ]);
    }

    public function byPeriod(PeriodEnum $period): static
    {
        // TODO должен быть способ сделать это проще
        $ds = static::DATE_SEPARATOR;
        match ($period) {
            PeriodEnum::year     => $this->selectRaw("YEAR(visited_at) as {$period->value}"),
            PeriodEnum::halfYear => $this->selectRaw("CONCAT_WS('{$ds}', LAST_DAY(visited_at) + interval 1 DAY - INTERVAL 1 MONTH - INTERVAL (MONTH(visited_at) - 1) MOD 6 MONTH, LAST_DAY(visited_at) + INTERVAL 1 DAY - INTERVAL 1 MONTH + INTERVAL (6 - (MONTH(visited_at) - 1) MOD 6) MONTH - INTERVAL 1 DAY) as {$period->value}"),
            PeriodEnum::quarter  => $this->selectRaw("CONCAT_WS('{$ds}', MAKEDATE(YEAR(visited_at), 1) + INTERVAL QUARTER(visited_at) - 1 QUARTER, LEAST(MAKEDATE(YEAR(visited_at), 1) + INTERVAL QUARTER(visited_at) QUARTER - INTERVAL 1 DAY, CURRENT_DATE)) as {$period->value}"),
            PeriodEnum::month    => $this->selectRaw("DATE_FORMAT(visited_at, '%Y.%m') as {$period->value}"),
            PeriodEnum::week     => $this->selectRaw("CONCAT_WS('{$ds}', DATE_ADD(visited_at, INTERVAL(-WEEKDAY(visited_at)) DAY), LEAST(DATE_ADD(visited_at, INTERVAL(6-WEEKDAY(visited_at)) DAY), CURRENT_DATE)) as {$period->value}"),
            PeriodEnum::day      => $this->selectRaw("DATE_FORMAT(visited_at, '%Y.%m.%d') as {$period->value}"),
        };

        $this->groupBy($period->value)->orderBy($period->value);

        $this->aggregations[] = $period->value;

        return $this;
    }

    protected function tryPeriod(string $period): static|false
    {
        $period = Str::replaceEnd('s', '', $period);
        $period = PeriodEnum::tryFrom($period);

        if ($period) {
            return $this->byPeriod($period);
        }

        return false;
    }

    protected function tryColumn(string $column): static|false
    {
        $column = $this->columnsMap[$column] ?? null;

        if (!$column) {
            return false;
        }

        $this->aggregations[] = $column;

        return $this->addSelect($column)->groupBy($column);
    }

    protected function tryMask(string $maskName): static|false
    {
        $mask = $this->masks[$maskName] ?? null;

        if (!$mask) {
            return false;
        }

        return $this->byMasks($mask['field'], $mask['masks'], $mask['title'] ?? $maskName, $mask['default'] ?? null);
    }

    public function domains(string $as = 'domain'): static
    {
        $this->aggregations[] = $as;

        // регуляркой проще, но так быстрее
        return $this->selectRaw("SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(visited_from, '/', 3), '://', -1), '/', 1), '?', 1) AS {$as}")
            ->groupBy($as);
    }

    public function byMasks(string $column, array $masks, string $as = null, string $default = 'n/a'): static
    {
        $as ??= $column;

        $query = $bindings = [];

        foreach ($masks as $mask => $match) {
            $query[] = "WHEN {$column} LIKE ? THEN ?";
            $bindings[] = $mask;
            $bindings[] = $match;
        }

        $query = implode("\n", $query);
        $query = "CASE $query ELSE ? END AS `{$as}`";

        $bindings[] = $default;

        $this->aggregations[] = $as;

        return $this->selectRaw($query, $bindings)->groupBy($as);
    }

    public function getAggregations(): array
    {
        return $this->aggregations;
    }

    public function getCount(): Collection
    {
        return $this->selectRaw('count(*) as count')->get();
    }
}