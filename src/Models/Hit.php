<?php

namespace Keasy9\HitStatistics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Keasy9\HitStatistics\Builders\HitBuilder;
use Keasy9\HitStatistics\Database\Factories\HitFactory;

/**
 * @method HitBuilder query()
 *
 * @mixin HitBuilder
 */
class Hit extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'visited_from',
        'visited_to',
        'country',
        'city',
        'useragent',
    ];

    protected static function newFactory(): HitFactory
    {
        return HitFactory::new();
    }

    public function newEloquentBuilder($query): HitBuilder
    {
        return new HitBuilder($query);
    }
}
