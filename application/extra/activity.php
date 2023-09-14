<?php

return [
    'redis_key_prefix' => [
        'lucky_draw_activity' => [
            'id' => 2,
            'info' => 'new:draw:config',
            'set' => 'new:draw:config',
        ],
        'lucky_draw_task' => [
            'info' => 'new:draw_task:',
            'set' => 'new:draw_task:set:list',
        ],
        'lucky_draw_prize' => [
            'info' => 'new:draw_prize:',
            'set' => 'new:draw_prize:set:',
        ],
        'vip_activity' => [
            'id' => 3,
            'info' => 'new:vip_activity:',
            'set' => 'new:vip_activity:set:',
        ],
        'cash_activity' => [
            'id' => 4,
            'info' => 'new:cash_activity:',
            'set' => 'new:cash_activity:set:',
        ],
        'activity_prize' => [
            'info' => 'new:prize:',
            'set' => 'new:prize:set:',
        ],
        'activity_task' => [
            'info' => 'new:task:',
            'set' => 'new:task:set:',
        ],
    ],
];
