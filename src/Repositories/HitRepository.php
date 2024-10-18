<?php

namespace Keasy9\HitStatistics\Repositories;

use BadMethodCallException;
use Eseath\SxGeo\Facades\SxGeo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\ForwardsCalls;
use Keasy9\HitStatistics\Builders\HitBuilder;
use Keasy9\HitStatistics\Dto\HitsAggregationDto;
use Keasy9\HitStatistics\Enums\PeriodEnum;
use Keasy9\HitStatistics\Models\Hit;
use Keasy9\HitStatistics\Models\HitsArchive;

/**
 * @method static days()
 * @method static byDays()
 * @method static weeks()
 * @method static byWeeks()
 * @method static months()
 * @method static byMonths()
 * @method static quarters()
 * @method static byQuarters()
 * @method static halfYears()
 * @method static byHalfYears()
 * @method static years()
 * @method static byYears()
 * @method static countries()
 * @method static byCountries()
 * @method static byCities()
 */
class HitRepository
{
    use ForwardsCalls;

    protected array $calls = [];
    protected bool $withArchive = false;

    protected function __construct(protected HitBuilder $builder)
    {
    }

    public function __call($method, $parameters): static
    {
        if (method_exists($this->builder, $method)) {
            $this->forwardCallTo(
                $this->builder,
                $method,
                $parameters,
            );
            return $this;
        }

        if ($method === 'last') {
            $this->builder->forPeriod(...$parameters);
            return $this;
        }

        $method = Str::replaceStart('by', '', $method);
        $method = Str::lcfirst($method);

        if (in_array($method, $this->calls)) {
            throw new BadMethodCallException("Аггрегация [$method] уже была добавлена!");
        }
        $this->calls[] = $method;

        $this->forwardCallTo(
            $this->builder,
            $method,
            $parameters,
        );

        return $this;
    }

    public static function __callStatic($name, $arguments): static
    {
        return static::query()->__call($name, $arguments);
    }

    public static function query(): static
    {
        return new static(Hit::query());
    }

    public function get(string $dtoClass = HitsAggregationDto::class): HitsAggregationDto
    {
        $data = $this->builder->getCount();
        $aggregations = collect($this->builder->getAggregations());

        $period = $aggregations->first(fn($aggregation) => PeriodEnum::tryFrom($aggregation));
        $aggregations = $aggregations->filter(fn($aggregation) => $aggregation !== $period);
        $period = PeriodEnum::from($period);

        /**
         * TODO получаются сплошные костыли. Надо придумать как хранить архивы так, чтобы это не создавало
         *  дополнительныъ ограничений и в то же время было удобно выбирать данные
         */
        if ($this->withArchive) {
            $archives = HitsArchive::query()
                ->groupBy($period->value, 'aggregationsKeys', 'aggregations')
                ->selectRaw('JSON_KEYS(aggregations) as aggregationsKeys')
                ->selectRaw('SUM(count) as count, aggregations');

            foreach ($aggregations as $aggregation) {
                $archives->havingRaw("JSON_CONTAINS(aggregationsKeys, ?, '$')", ["\"{$aggregation}\""]);
            }

            $archives->havingRaw('JSON_LENGTH(aggregationsKeys) = ' . count($aggregations));

            // TODO найти способ переиспользовать запросы из hitBuilder или абстрагировать их куда-то
            $ds = HitBuilder::DATE_SEPARATOR;
            switch ($period) {
                case PeriodEnum::year:
                    $archives->selectRaw("YEAR(period_start) as {$period->value}")
                        ->whereRaw('YEAR(period_start) = YEAR(period_end)');
                    break;
                case PeriodEnum::halfYear:
                    $archives->selectRaw("CONCAT_WS('{$ds}', LAST_DAY(period_start) + interval 1 DAY - INTERVAL 1 MONTH - INTERVAL (MONTH(period_start) - 1) MOD 6 MONTH, LAST_DAY(period_start) + INTERVAL 1 DAY - INTERVAL 1 MONTH + INTERVAL (6 - (MONTH(period_start) - 1) MOD 6) MONTH - INTERVAL 1 DAY) as {$period->value}")
                        ->whereRaw('TIMESTAMPDIFF(QUARTER, period_start, period_end) BETWEEN 0 AND 2');
                    break;
                case PeriodEnum::quarter:
                    $archives->selectRaw("CONCAT_WS('{$ds}', MAKEDATE(YEAR(period_start), 1) + INTERVAL QUARTER(period_start) - 1 QUARTER, LEAST(MAKEDATE(YEAR(period_start), 1) + INTERVAL QUARTER(period_start) QUARTER - INTERVAL 1 DAY, CURRENT_DATE)) as {$period->value}")
                        ->whereRaw('TIMESTAMPDIFF(YEAR, period_start, period_end) = 0');
                    break;
                case PeriodEnum::month:
                    $archives->selectRaw("DATE_FORMAT(period_start, '%Y.%m') as {$period->value}")
                        ->whereRaw('TIMESTAMPDIFF(QUARTER, period_start, period_end) = 0');
                    break;
                case PeriodEnum::week:
                    $archives->selectRaw("CONCAT_WS('{$ds}', DATE_ADD(period_start, INTERVAL(-WEEKDAY(period_start)) DAY), LEAST(DATE_ADD(period_start, INTERVAL(6-WEEKDAY(period_start)) DAY), CURRENT_DATE)) as {$period->value}")
                        ->whereRaw('TIMESTAMPDIFF(WEEK, period_start, period_end) = 0');
                    break;
                case PeriodEnum::day:
                    $archives->selectRaw("DATE_FORMAT(period_start, '%Y.%m.%d') as {$period->value}")
                        ->whereRaw('TIMESTAMPDIFF(DAY, period_start, period_end) = 0');
                    break;
            }

            $archives = $archives->get()->map(function (HitsArchive $archive) use ($period, $aggregations) {
                    $result = ['count' => (int)$archive->count];

                    if ($period) {
                        $result[$period->value] = $archive->getAttribute($period->value);
                    }

                    foreach ($aggregations as $aggregation) {
                        $result[$aggregation] = $archive->aggregations[$aggregation];
                    }

                    return $result;
                });

            $data = $archives->merge($data);
        }

        return new $dtoClass(
            $data,
            $this->builder->getAggregations(),
        );
    }

    public static function addFromRequest(Request $request): Hit
    {
        $country = SxGeo::get($request->ip());
        $city = null;

        if (!$country) {
            $country = null;

        } elseif (is_array($country)) {
            $city = $country['city']['name_en'];
            $country = $country['country']['iso'];
        }

        $hit = new Hit([
            'visited_from' => urldecode($request->header('referer')),
            'visited_to'   => $request->fullUrl(),
            'country'      => $country,
            'city'         => $city,
            'useragent'    => $request->header('User-Agent'),
        ]);

        $hit->save();

        return $hit;
    }

    public function withArchive(bool $withArchive = true): static
    {
        $this->withArchive = $withArchive;

        return $this;
    }
}