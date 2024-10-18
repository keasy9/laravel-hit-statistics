<?php

namespace Keasy9\HitStatistics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HitsArchive extends Model
{
    protected $casts = [
        'aggregations' => 'array',
    ];
}
