<?php

use Keasy9\HitStatistics\Enums\PeriodEnum;

return [
    // срок хранения данных или false для бесконечного хранения
    'lifetime'         => PeriodEnum::month,

    // срок хранения архивов или false для бесконечного хранения
    'archive_lifetime' => PeriodEnum::year,

    // учитывать авторизированнх пользователей
    'authorized'       => false,

    // аггрегации по маскам
    'masked'           => [
        'systems' => [
            'field'   => 'useragent',
            'default' => 'other',
            'title'   => 'system',
            'masks'   => [
                '%Mac%OS%'  => 'mac OS',
                '%imac%'    => 'mac OS',
                '%iPad%'    => 'IOS',
                '%iPod%'    => 'IOS',
                '%iPhone%'  => 'IOS',
                '%android%' => 'android',
                '%linux%'   => 'linux',
                '%win%'     => 'windows',
            ],
        ],

        'devices' => [
            'field'   => 'useragent',
            'default' => 'unknown',
            'title'   => 'device',
            'masks'   => [
                '%Mac%OS%'  => 'desktop',
                '%imac%'    => 'desktop',
                '%iPad%'    => 'tablet',
                '%iPod%'    => 'mobile',
                '%iPhone%'  => 'mobile',
                '%android%' => 'mobile',
                '%linux%'   => 'desktop',
                '%win%'     => 'desktop',
            ],
        ],

        'browsers' => [
            'field'   => 'useragent',
            'default' => 'other',
            'title'   => 'browser',
            'masks'   => [
                '%edge%'    => 'edge',
                '%MSIE%'    => 'IE',
                '%Firefox%' => 'firefox',
                '%Chrome%'  => 'chrome',
                '%Safari%'  => 'safari',
                '%Opera%'   => 'opera',
            ],
        ],
    ],

    // типы архивов
    'archive_types'    => [
        'default' => [
            ['domains', PeriodEnum::month],
            ['countries', PeriodEnum::month],
            ['devices', PeriodEnum::month],
        ],
    ],
];