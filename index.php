<?php

include __DIR__.'/lib/MSVC.php';
$app = new MSVC();
$app::init('');
echo $app::start('MSVC');
