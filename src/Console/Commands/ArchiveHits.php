<?php

namespace Keasy9\HitStatistics\Console\Commands;

use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Keasy9\HitStatistics\Builders\HitBuilder;
use Keasy9\HitStatistics\Enums\PeriodEnum;
use Keasy9\HitStatistics\Models\Hit;
use Keasy9\HitStatistics\Models\HitsArchive;
use Keasy9\HitStatistics\Repositories\HitRepository;

class ArchiveHits extends Command implements Isolatable
{
    protected $signature = 'hits:archive {--keep} {--before=} {--type=default}';

    protected $description = 'Архивировать все хиты старше указанной даты';

    public function handle(): int
    {
        if (!config('hit.archive') && !config('hits.lifetime')) {
            $this->warn('Архивация отключена.');
            return static::INVALID;
        }

        $keep = $this->option('keep');
        $type = $this->option('type');

        $before = $this->option('before');
        if ($before) {
            try {
                $before = Carbon::parse($before);
            } catch (Exception) {
                $this->error("А [{$before}] - это вообще дата? Не могу разобрать.");
                return static::FAILURE;
            }

        } else {
            $now = Carbon::now();
            $before = match (config('hits.lifetime')) {
                PeriodEnum::year     => $now->startOfYear(),
                PeriodEnum::halfYear => ($now->quarter > 2) ? $now->endOfYear() : $now->month(7)->startOfMonth(),
                PeriodEnum::quarter  => $now->startOfQuarter(),
                PeriodEnum::month    => $now->startOfMonth(),
                PeriodEnum::week     => $now->startOfWeek(),
                PeriodEnum::day      => $now->startOfDay(),
                default              => false,
            };

            if (!$before) {
                $this->warn('Не указан стандартный срок хранения данных и не передан другой.');
                return static::INVALID;
            }
        }

        $this->line("Архивирую данные до [{$before}].");

        $type = config("hits.archive_types.{$type}");
        if (is_array($type[0])) {
            // сгруппированные конфигурации
            foreach ($type as $t) {
                $this->makeArchive($before, $t);
            }

        } else {
            $this->makeArchive($before, $type);
        }

        if (!$keep) {
            $this->newLine();
            $this->line("Удаляю данные до [{$before}].");
            Hit::where('visited_at', '<', $before)->delete();
        }

        return static::SUCCESS;
    }

    protected function makeArchive(Carbon $before, array $aggregations): bool
    {
        $this->newLine();

        $aggs = array_map(fn($agg) => $agg instanceof PeriodEnum ? $agg->value : $agg, $aggregations);
        $this->line('Архивирую по [' . implode(', ', $aggs) . ']');

        $period = null;
        $data = HitRepository::query();
        foreach ($aggregations as $aggregation) {
            if (!$period && $aggregation instanceof PeriodEnum) {
                $period = $aggregation;
                $data->byPeriod($period);
                continue;
            }
            $data->$aggregation();
        }

        $data = $data->where('visited_at', '<', $before)->get();

        if ($data->isEmpty()) {
            $this->warn('Нечего архивировать.');
            return false;
        }

        $archive = [];
        $this->withProgressBar(
            $data->getData()->map(fn(Hit $hit) => $hit->toArray()),
            function ($hit) use ($period, &$archive) {
                $a = [
                    'count'        => $hit['count'],
                    'aggregations' => collect($hit)->except('count', $period->value)->toJson(JSON_UNESCAPED_UNICODE),
                ];

                if ($period) {
                    $a = array_merge($a, $this->getStartEndDates($hit, $period));
                }

                $archive[] = $a;
            }
        );

        return HitsArchive::insert($archive);
    }

    protected function getStartEndDates(iterable $hit, PeriodEnum $for): array
    {
        if (PeriodEnum::year === $for) {
            $date = Carbon::createFromFormat('Y', $hit['year']);
            $periodStart = $date->copy()->startOfYear();
            $periodEnd = $date->endOfYear();

        } elseif (PeriodEnum::month === $for) {
            $date = Carbon::createFromFormat('Y.m', $hit['month']);
            $periodStart = $date->copy()->startOfMonth();
            $periodEnd = $date->endOfMonth();

        } elseif (PeriodEnum::day === $for) {
            $date = Carbon::createFromFormat('Y.m.d', $hit['day']);
            $periodStart = $date->copy()->startOfDay();
            $periodEnd = $date->endOfDay();

        } else {
            $date = explode(HitBuilder::DATE_SEPARATOR, $hit[$for->value]);
            $periodStart = $date[0];
            $periodEnd = $date[1];
        }

        return [
            'period_start' => $periodStart,
            'period_end'   => $periodEnd,
        ];
    }
}
