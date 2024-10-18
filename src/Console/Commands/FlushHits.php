<?php

namespace Keasy9\HitStatistics\Console\Commands;

use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Keasy9\HitStatistics\Enums\PeriodEnum;
use Keasy9\HitStatistics\Models\Hit;
use Keasy9\HitStatistics\Models\HitsArchive;

class FlushHits extends Command
{
    protected $signature = 'hits:flush {--type=both} {--before=}';

    protected $description = 'Удалить все хиты и архивы старше указанной даты';

    public function handle(): int
    {
        $type = $this->option('type');

        $hitsBefore = $archiveBefore = $before = $this->option('before');
        if ($before) {
            try {
                $hitsBefore = $archiveBefore = Carbon::parse($before);
            } catch (Exception) {
                $this->error("А [{$before}] - это вообще дата? Не могу разобрать.");
                return static::FAILURE;
            }
        }

        if ($type === 'both') {
            $type = ['data', 'archive'];
        } else {
            $type = [$type];
        }

        if (in_array('data', $type)) {
            if (!$hitsBefore) {
                $now = Carbon::now();
                $hitsBefore = match (config('hits.lifetime')) {
                    PeriodEnum::year     => $now->startOfYear(),
                    PeriodEnum::halfYear => ($now->quarter > 2) ? $now->endOfYear() : $now->month(7)->startOfMonth(),
                    PeriodEnum::quarter  => $now->startOfQuarter(),
                    PeriodEnum::month    => $now->startOfMonth(),
                    PeriodEnum::week     => $now->startOfWeek(),
                    PeriodEnum::day      => $now->startOfDay(),
                    default              => false,
                };

                if (!$hitsBefore) {
                    $this->warn('Не указан стандартный срок хранения данных и не передан другой.');
                    return static::INVALID;
                }
            }

            $this->line("Удаляю данные до [{$hitsBefore}].");
            Hit::where('visited_at', '<', $hitsBefore)->delete();
        }

        if (in_array('archive', $type)) {
            if (!$archiveBefore) {
                $now = Carbon::now();
                $archiveBefore = match (config('hits.archive_lifetime')) {
                    PeriodEnum::year     => $now->startOfYear(),
                    PeriodEnum::halfYear => ($now->quarter > 2) ? $now->endOfYear() : $now->month(7)->startOfMonth(),
                    PeriodEnum::quarter  => $now->startOfQuarter(),
                    PeriodEnum::month    => $now->startOfMonth(),
                    PeriodEnum::week     => $now->startOfWeek(),
                    PeriodEnum::day      => $now->startOfDay(),
                    default              => false,
                };

                if (!$archiveBefore) {
                    $this->warn('Не указан стандартный срок хранения архива и не передан другой.');
                    return static::INVALID;
                }
            }

            $this->line("Удаляю архив до [{$archiveBefore}].");
            HitsArchive::where('period_end', '<', $archiveBefore)->delete();
        }

        return static::SUCCESS;
    }
}
