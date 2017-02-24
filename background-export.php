<?php

use go1\MbosiExport\App;

return call_user_func(function ($class, $bucket, $key, $fields, $params, $selectedIds, $excludedIds) {
    require_once __DIR__ . '/../../autoload.php';

    $conf = is_file(__DIR__ . '/../../../config.php') ? __DIR__ . '/../../../config.php' : __DIR__ . '/../../../config.default.php';
    $app = new $class(require $conf);

    // Init variables.
    $exportHelper = $app['go1.report_helpers.export'];
    $s3Client = $app['go1.report_helpers.s3'];
    $elasticsearchClient = $app['go1.report_helpers.elasticsearch'];

    $exportHelper->doExport($bucket, $key, $fields, $params, $selectedIds, $excludedIds);
}, $argv[1], $argv[2], $argv[3], json_decode($argv[4], true), json_decode($argv[5], true), json_decode($argv[6], true), json_decode($argv[7], true));
