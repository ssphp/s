<?php

$configs = [
    /*
    |--------------------------------------------------------------------------
    | HTTP server configurations.
    |--------------------------------------------------------------------------
    |
    | @see https://www.swoole.co.uk/docs/modules/swoole-server/configuration
    |
     */
    'server' => [
        'host' => '10.52.23.17',
        'port' => '1215',
        'public_path' => __DIR__ . '/public',
        // Determine if to use swoole to respond request for static files
        'handle_static_files' => true,
        'options' => [
            'pid_file' => __DIR__ . '/logs/ssw.pid',
            'log_file' => __DIR__ . '/logs/ssw.log',
            'daemonize' => false,
            // Normally this value should be 1~4 times larger according to your cpu cores.
            'reactor_num' => 4,
            'worker_num' => 8,
            'task_worker_num' => 4,
            // The data to receive can't be larger than buffer_output_size.
            'package_max_length' => 20 * 1024 * 1024,
            // The data to send can't be larger than buffer_output_size.
            'buffer_output_size' => 10 * 1024 * 1024,
            // Max buffer size for socket connections
            'socket_buffer_size' => 128 * 1024 * 1024,
            // Worker will restart after processing this number of request
            'max_request' => 3000,
            // Enable coroutine send
            'send_yield' => true,
            // You must add --enable-openssl while compiling Swoole
            'ssl_cert_file' => null,
            'ssl_key_file' => null,
        ],
    ],
    /*
    |--------------------------------------------------------------------------
    | 是否启用服务注册&发现
    |--------------------------------------------------------------------------
     */
    'service_enable' => true,
    /*
    |--------------------------------------------------------------------------
    | Enable to turn on websocket server.
    |--------------------------------------------------------------------------
     */
    'websocket' => [
        'enabled' => false,
    ],
    /*
    |--------------------------------------------------------------------------
    | Console output will be transferred to response content if enabled.
    |--------------------------------------------------------------------------
     */
    'ob_output' => true,
    /*
    |--------------------------------------------------------------------------
    | Instances here will be cleared on every request.
    |--------------------------------------------------------------------------
     */
    'instances' => [
    ],
    /*
    |--------------------------------------------------------------------------
    | Define your swoole tables here.
    |
    | @see https://www.swoole.co.uk/docs/modules/swoole-table
    |--------------------------------------------------------------------------
     */
    'tables' => [
        // 'table_name' => [
        //     'size' => 1024,
        //     'columns' => [
        //         ['name' => 'column_name', 'type' => Table::TYPE_STRING, 'size' => 1024],
        //     ]
        // ],
    ],
];
