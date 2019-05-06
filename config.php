<?php

$config = [

    // 指定站点映射关系 , 只在根配置下有效, default表示未指出的站点，如未设置 default 将拒绝不在列表的域名进行访问
    'SITE' => [
        'default' => 'app',
    ],
    'DB' => [
        'fuwu' => ['mysql:host=rds9gxus99vmbf49g3bi.mysql.rds.aliyuncs.com:3306;dbname=hfjylmstest_temp', 'hfjylmstest_temp', 'bUFNZzA2akU='],
        'fuwuwrite' => ['mysql:host=rds9gxus99vmbf49g3bi.mysql.rds.aliyuncs.com:3306;dbname=hfjylmstest_temp', 'hfjylmstest_temp', 'bUFNZzA2akU='],
        //'fuwu'    => [ 'mysql:host=mysql.hfjy.red;dbname=hfshop', 'lxf', 'UzRtQzc0eGxTRE1uSldzUQ==' ],
        //'flow'    => [ 'mysql:host=mysqlhfjy.red;dbname=work_flow', 'lxf', 'UzRtQzc0eGxTRE1uSldzUQ==' ],
    ],
    'VIEW_REPLACE' => [

    ],

];
