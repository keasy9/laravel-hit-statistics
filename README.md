# Пакет Laravel для сбора статистики посещений сайта.

### Установка:
```bash
    composer require keasy9/laravel-hit-statistics

    php artisan migrate
    php artisan vendor:publish
```

### Использование:

```php
    use Keasy9\HitStatistics\Http\Middleware\CollectHit;

    Route::middleware(CollectHit::class)->group(function () {
        //...
    });
```

```php
    use Keasy9\HitStatistics\Repositories\HitRepository;
    use Keasy9\HitStatistics\Dto\OrchidAggregationDto;
    
    HitRepository::domains()->byDays()->withArchive()->get()->toArray();
    // вернёт данные, сгруппированные по дням, включая архивированные данные
    
    HitRepository::devices()->byCountries()->get(OrchidAggregationDto::class)->toArray();
    // вернёт данные в совместимом формате для вывода в админке orchid/platform
```

```bash
    php artisan hits:archive
        --keep                #не удалять данные, которые попадут в архив
        --type=default        #тип архива из конфига
        --before=""           #архивировать данные до указанной даты. По умолчанию определяется конфигом
        
    php artisan hits:flush
        --before=""           #удалить данные до указанной даты. По умолчанию определяется конфигом
        --type=both           #что удалять:
                              #    data - только хиты
                              #    archive - только архивы
                              #    both - хиты и архивы 
```

### Архивы

Чтобы уменьшить размер хранимых данных на часто посещаемых сайтах, предусмотрена архивация.
Архив создаётся за заданный период (день, месяц, год и т.п.) и по заданным аггрегациям.
Для одного периода можно создавать разные архивы (по разным аггрегациям). Типы архивов указываются в конфигурации.
Например: 
* архивировать данные по доменам, с которых были переходы на сайт, группируя за месяц;
* архивировать данные по устройствам поситетелей и странам;

#### Рекомендуется настроить периодическую архивацию:
```php
namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Keasy9\HitStatistics\Console\Commands\ArchiveHits;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command(ArchiveHits::class)->monthly();
    }
    
    //...
}
```

### Конфиденциальность
Пакет собирает следующие данные о посетителях сайта:
* местоположение (по ip),
* браузер, система и устройство (по заголовку useragent),
* предыдущий адрес, с которого произошёл переход на сайт (по заголовку http_referer).

Для определения местоположения пользователя используется пакет [eseath/sypexgeo](https://github.com/Eseath/sypexgeo).
В зависимости от указанной в его конфигурации БД местоположений, будут сохранены страна и город посетителя.

Законодательство некоторых стран требует, чтобы сайт предупреждал пользователя
или давал ему возможность отказаться от сбора данных. В любом случае, пользователь этого пакета
самостоятельно несёт ответственность за все способы его использования.
