<?php

use Hodos\Base\Stack;

/* Start App Session */
session_name(env('APP_NAME') ?? 'xstack');
session_start();

ini_set('date.timezone', config('app.timezone') ?? date_default_timezone_get());

ini_set('log_errors', config('app.log_errors') ?? false);
ini_set('error_log', ROOT . '/system/framework/logs/errors.log');
ini_set('display_errors', config('app.display_errors') ?? true);

/* Require the constants file */
require_once ROOT . '/vendor/xhodos/framework/constants.php';

/* Require the web routes file */
require_once ROOT . '/routes/web.php';

$app = Stack::instantiate();
$app->push();
