<?php

use Hodos\Base\Dir;
use Hodos\Base\Route;
use Hodos\Base\Router;
use Hodos\Base\ValidatorResponse;
use Hodos\Stack\Errors\ViewError;
use Hodos\Stack\Template\Engine;

$getBaseRequestURI = fn (int $offset) => implode('/', array_slice(explode('/', REQUEST_URI), $offset));
$getBaseURI = fn (int $index) => explode('/', REQUEST_URI)[$index];

if (!function_exists('asset')) {
	function asset($path):string
	{
		return BASE_URI . '/' . (env('APP_ASSETS_DIR') ?? 'public') . '/' . $path;
	}
}

if (!function_exists('config')) {
	/**
	 * @param string $name
	 * @param string $default
	 * @return mixed
	 */
	function config(string $name, string $default = ''):mixed
	{
		$exploded = explode('.', $name);
		$file = array_slice($exploded, 0, 1)[0] . '.php';
		$key = implode('.', array_splice($exploded, 1));
		$config = include getRootPath() . "/config/$file";
		return count($config) ? (!!key_exists($key, $config) ? $config[$key] : $default) : $default;
	}
}


if (!function_exists('env')) {
	function env($name)
	{
		$env = parse_ini_file(getRootPath() . '/.env');
		return key_exists($name, $env) ? $env[$name] : NULL;
	}
}

if (!function_exists('constructViewFilePath')) {
	function constructViewFilePath(string $view):string
	{
		$path_construct = '';
		$path_array = preg_split('/[.]/', $view);
		
		foreach ($path_array as $value)
			$path_construct .= $value . (end($path_array) === $value ? NULL : '/');
		return $path_construct;
	}
}

if (!function_exists('getRootPath')) {
	function getRootPath():false|string|null
	{
		return defined('PROJECT_ROOT') ? PROJECT_ROOT : (defined('ROOT') ? ROOT : Dir::root());
	}
}

if (!function_exists('current_route')) {
	function current_route(string ...$uri)
	{
		if (!empty($uri)) {
			$match = false;
			foreach ($uri as $value)
				if (request()->route->generatedURI === $value) {
					$match = true;
					break;
				}
			return $match;
		}
		return request()->route->uri;
	}
}

if (!function_exists('dd')) {
	function dd(...$vars):never
	{
		http_response_code(500);
		foreach ($vars as $var) {
			echo '<pre>';
			(gettype($var) === 'array' || gettype($var) === 'object') ? print_r($var) : print $var;
			echo '</pre>';
		}
		exit;
	}
}

if (!function_exists('getUnderscoredClassName')) {
	function getUnderscoredClassName($class):string
	{
		$called_class_exploded = explode(DIRECTORY_SEPARATOR, useDirectorySeparator($class));
		$class_name = end($called_class_exploded);
		return getUnderscoredName($class_name);
	}
}

if (!function_exists('getUnderscoredName')) {
	function getUnderscoredName($name):string
	{
		$underscored = '';
		$chars = str_split($name);
		
		foreach ($chars as $key => $char)
			$underscored .= $key && preg_match("/[A-Z]/", $char) ? "_$char" : $char;
		return strtolower($underscored);
	}
}

if (!function_exists('getViewFile')) {
	function getViewFile($file):string
	{
		$path = (env('APP_VIEWS_DIR') ?? 'views') . '/' . $file;
		return str_replace('\\', '/', getRootPath() . DIRECTORY_SEPARATOR . useDirectorySeparator($path) . '.php');
	}
}

if (!function_exists('loadFile')) {
	function loadFile($path, ?array $data = NULL)
	{
		$file = str_replace('\\', '/', getRootPath() . DIRECTORY_SEPARATOR . useDirectorySeparator($path) . '.php');
			
		if (is_readable($file)) {
			if (!empty($data))
				extract($data);
			require_once $file;
			return $file;
		}
		return false;
	}
}

function errorBag()
{
	return ValidatorResponse::$errors;
}

if (!function_exists('request')) {
	/**
	 * Summary of request
	 *
	 * @return object{route:object{action:array,generatedURI:string,uri:string,method:'any|get|post|put|patch|delete',is_named:bool,name:string},request:object}
	 */
	function request():object
	{
		return Route::currentStack()['stack-info'];
	}
}

if (!function_exists('route')) {
	function route(string $name)
	{
		return !empty($name) && !empty(Router::routes($name)) ? Router::routes($name)->generatedURI : throw new Exception("Route name cannot be empty", 1);
	}
}

if (!function_exists('response')) {
	function response(string|array|object $data = NULL, $status = 200)
	{
		http_response_code($status);
		return print (is_array($data) || is_object($data)) ? json_encode($data) : $data;
	}
}

if (!function_exists('view')) {
	function view(string $view, ?array $data = NULL)
	{
		$path_construct = constructViewFilePath($view);
		$path = (env('APP_VIEWS_DIR') ?? 'views') . '/' . $path_construct;
		
		if (is_readable(ROOT . DIRECTORY_SEPARATOR . useDirectorySeparator($path) . '.xs.php'))
			return print new Engine($view, $data)->render();
		dd(new ViewError('View ' . $view . ' not found', 404));
	}
}

/*if (!function_exists('view')) {
	function view(string $view, ?array $data = NULL)
	{
		$path_construct = constructViewFilePath($view);
		$path = (env('APP_VIEWS_DIR') ?? 'views') . '/' . $path_construct;
		
		if (is_readable(str_replace('\\', '/', getRootPath() . DIRECTORY_SEPARATOR . useDirectorySeparator($path) . '.php'))) {
			$template = new RenderView($path_construct);
			
			if (!empty($data))
				foreach ($data as $key => $value)
					$template->$key($value);
			return print $template;
		}
		dd(new ViewError('View ' . $view . ' not found', 404));
	}
}*/

/*if (!function_exists('viewTest')) {
	function viewTest(string $view, ?array $data = NULL)
	{
		$path_construct = constructViewFilePath($view);
		$path = (env('APP_VIEWS_DIR') ?? 'views') . '/' . $path_construct;
		
		if (is_readable(getRootPath() . DIRECTORY_SEPARATOR . useDirectorySeparator($path) . '.xs.php'))
			return print new Engine($view, $data)->render();
		dd(new ViewError('View ' . $view . ' not found', 404));
	}
}*/


if (!function_exists('useDirectorySeparator')) {
	function useDirectorySeparator($path)
	{
		return str_replace('/', DIRECTORY_SEPARATOR, $path);
	}
}

// RenderView Helpers
if (!function_exists('attach')) {
	function attach(string $view, ?array $data = NULL)
	{
		view($view, $data);
	}
}

if (!function_exists('extendview')) {
	/**
	 * Summary of extendview
	 *
	 * @param mixed $view
	 * @param mixed $data
	 * @return int
	 */
	function extendview($view, ?array $data = NULL):int
	{
		return RenderView::extend($view, $data);
	}
}

if (!function_exists('endpush')) {
	/**
	 * Summary of endpush
	 *
	 * @return bool|string
	 */
	function endpush():bool|string
	{
		return RenderView::endsection();
	}
}

if (!function_exists('push')) {
	/**
	 * Summary of push
	 *
	 * @param mixed $name
	 * @param mixed $keep
	 * @param mixed $callbacks
	 * @return mixed
	 */
	function push($name, $keep = false, $callbacks = NULL):mixed
	{
		return RenderView::section($name, $keep, $callbacks);
	}
}

if (!function_exists('stack')) {
	/**
	 * Summary of stack
	 *
	 * @param mixed $name
	 * @return array|string|null
	 */
	function stack($name):array|string|null
	{
		return RenderView::stack($name);
	}
}
