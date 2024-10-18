<?php

declare(strict_types=1);

namespace Keasy9\HitStatistics\Providers;

use Illuminate\Support\ServiceProvider;
use Keasy9\HitStatistics\Console\Commands\FlushHits;
use Keasy9\HitStatistics\Console\Commands\ArchiveHits;

class HitStatisticsServiceProvider extends ServiceProvider
{
    public function register()
    {
        parent::register();
    }

    public function boot()
    {
        $packageDir = __DIR__.'/../';

        $this->commands([
            FlushHits::class,
            ArchiveHits::class,
        ]);

        $this->mergeConfigFrom(
            "{$packageDir}/config/hits.php", 'hits'
        );

        $this->publishes([
            "{$packageDir}/config/hits.php" => config_path('hits.php'),
        ]);

        $this->loadMigrationsFrom(["{$packageDir}/database/migrations"]);
    }
}
