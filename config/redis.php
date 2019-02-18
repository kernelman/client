<?php
/**
 *
 * Author:  Kernel Huang
 * Mail:    kernelman79@gmail.com
 * Date:    1/11/19
 * Time:    5:09 PM
 */

return (object)[

    // Redis sync client config.
    'sync' => (object)[

        'REDIS_DB'          =>  0,
        'REDIS_HOST'        =>  '127.0.0.1',
        'REDIS_PASSWORD'    =>  'mintos',
        'REDIS_PORT'        =>  6379,
        'REDIS_LIFETIME'    =>  120,
        'REDIS_PERSISTENT'  =>  true,
        'REDIS_PREFIX'      =>  ''
    ],
];
