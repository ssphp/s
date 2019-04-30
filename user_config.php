<?php

$config = [
    'SITE' => [
        'default' => 'app'
    ],
    'DB' => [
        //'fuwu'  => [ 'mysql:host=192.168.0.215:3306;dbname=hls_test2', 'qishaobo1', 'TUdlWWxCaFRoS1pNbXBGYg==' ],
        /*'fuwuwrite'  => [ 'mysql:host=192.168.0.215:3306;dbname=hls_test2', 'qishaobo1', 'TUdlWWxCaFRoS1pNbXBGYg==' ],*/
        'tool' => ['mysql:host=localhost:3306;dbname=tool', 'root', 'cm9vdA=='],
        //'tool' => ['mysql:host=localhost:3306;dbname=tool', 'test', 'MTIzNDU2'],
        'toolwrite' => ['mysql:host=localhost:3306;dbname=tool', 'root', 'cm9vdA=='],
//        'fuwu' => ['mysql:host=121.40.170.38:3311;dbname=i_test', 'gql_export', 'eDdBVUk2WlBnajBqOTFORQ=='],
        'fuwu' => ['mysql:host=mysql-a.hfjy.com:3306;dbname=hls_test2', 'hls', 'aGxzQDE5Mi4xNjguMC4yMDAjMTIzNDU2'],
        'fuwuwrite' => ['mysql:host=mysql-a.hfjy.com:3306;dbname=hls_test2', 'hls', 'aGxzQDE5Mi4xNjguMC4yMDAjMTIzNDU2'],
//        'fuwu' => ['rm-bp11666r7y6x7z9zpao.mysql.rds.aliyuncs.com:3306;dbname=i_test', 'gql_readonly', 'vQW4UYTgRAhaiV7L'],
//        'fuwuwrite' => ['rm-bp11666r7y6x7z9zpao.mysql.rds.aliyuncs.com:3306;dbname=i_test', 'gql_readonly', 'vQW4UYTgRAhaiV7L'],
    ],
    'VIEW_REPLACE' => [

    ],
];
