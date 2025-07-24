<?php
if (!isset($_SERVER['REQUEST_SCHEME']))
	$_SERVER['REQUEST_SCHEME'] = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

if (!isset($_SERVER['QUERY_STRING']))
	$_SERVER['QUERY_STRING'] = '';

define('ENV', env('APP_ENV'));
define('ROOT_SPLIT', explode('\\', getRootPath()));
define('ROOT_SPLIT_COUNT', count(ROOT_SPLIT));

define('HOST', $_SERVER['HTTP_HOST']);
define('REQUEST_URI', $_SERVER['REQUEST_URI']);
define('QUERY_STRING', $_SERVER['QUERY_STRING']);
define('REQUEST_METHOD', $_SERVER['REQUEST_METHOD']);
define('REQUEST_SCHEME', $_SERVER['REQUEST_SCHEME']);

define('SERVER_NAME', $_SERVER['SERVER_NAME']);
define('SERVER_SOFTWARE', $_SERVER['SERVER_SOFTWARE']);
define('IS_LOCALHOST', str_contains(mb_strtolower($_SERVER['SERVER_NAME']), 'localhost'));

/* Build URI's */
define('BASE_REQUEST_URI', IS_LOCALHOST ? $getBaseRequestURI(2) : $getBaseRequestURI(1));
define('URI', REQUEST_SCHEME . '://' . explode('?', HOST . REQUEST_URI)[0]);
define('BASE_URI', REQUEST_SCHEME . '://' . HOST . (IS_LOCALHOST ? '/' . $getBaseURI(1) : $getBaseURI(0)));
define('FULL_URI', URI . (!empty(QUERY_STRING) ? '?' . QUERY_STRING : NULL));
