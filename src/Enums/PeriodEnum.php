<?php

namespace Keasy9\HitStatistics\Enums;

enum PeriodEnum: string
{
    case year = 'year';
    case halfYear = 'halfYear';
    case quarter = 'quarter';
    case month = 'month';
    case week = 'week';
    case day = 'day';
}
