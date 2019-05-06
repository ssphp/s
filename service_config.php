<?php

$configs = [
    /*---------------------------------------------------------------微服务相关配置-----------------------------------------------------------------------*/
    'service' => [
        'appName' => 'c1',
        'weight' => '1',
        'registryPrefix' => '',
    ],
    //微服务redis地址
    'redis' => [
        'address' => '127.0.0.1',
        'port' => 6379,
        'password' => '',
        'database' => 15, //默认库
    ],
    /*--------------------------------------------------------     微服务相关配置 end     ----------------------------------------------------------------*/
];