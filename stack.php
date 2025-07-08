<?php

use Hodos\Base\Stack;

/* Form Session name */
$session_name = preg_replace("/[0-9]/", '', env('APP_NAME') ?? 'xhodos');
$session_name = preg_replace('/[ \t\r\n]/', '_', $session_name);

define('SESSION_NAME', $session_name);

/* Start App Session */
session_name(SESSION_NAME);
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
