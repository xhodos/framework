<?php

use Hodos\Base\Dir;
use Hodos\Base\Route;
use Hodos\Base\Router;
use Hodos\Base\Validator;
use Hodos\Base\ValidatorResponse;
use Hodos\Stack\Errors\ViewError;
use Hodos\Stack\Template\Engine;
use Hodos\Stack\Template\View;
use Hodos\Stack\XObject;
use Hodos\Stack\Support\Cache;
use setasign\Fpdi\Fpdi;


$getBaseRequestURI = fn (int $offset) => implode('/', array_slice(explode('/', REQUEST_URI), $offset));
$getBaseURI = fn (int $index) => explode('/', REQUEST_URI)[$index];


if (!function_exists('asset')) {
	function asset($path):string
	{
		return correctDirPath(BASE_URI . '/' . env('APP_ASSETS_DIR', 'public') . '/' . $path);
	}
}

if (!function_exists('autoDateTimeParser')) {
	function autoDateTimeParser(string|DateTime $datetime, ?DateTime $reference = NULL):string
	{
		$date = $datetime instanceof DateTime ? $datetime : new DateTime($datetime);
		$now = $reference ?? new DateTime();
		
		$diff = $now->diff($date);
		$isPast = $now > $date;
		
		$units = [
			'y' => 'year',
			'm' => 'month',
			'd' => 'day',
			'h' => 'hour',
			'i' => 'minute',
			's' => 'second',
		];
		
		foreach ($units as $key => $label) {
			$value = $diff->$key;
			
			// Handle weeks specially from days
			if ($key === 'd' && $value >= 7) {
				$weeks = floor($value / 7);
				return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ($isPast ? ' ago' : ' from now');
			}
			if ($value > 0)
				return $value . ' ' . $label . ($value > 1 ? 's' : '') . ($isPast ? ' ago' : ' from now');
		}
		return 'just now';
	}
	
}

if (!function_exists('cache')) {
	function cache():Cache
	{
		static $cache = NULL;
		if (!$cache)
			$cache = new Cache();
		return $cache;
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

if (!function_exists('correctDirPath')) {
	function correctDirPath(string $path):string
	{
		return str_replace('\\', '/', $path);
	}
}

function csrf_token():string
{
	if (session_status() !== PHP_SESSION_ACTIVE)
		session_start();
	
	if (empty($_SESSION['_csrf_token']))
		$_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
	return $_SESSION['_csrf_token'];
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
		if (!headers_sent())
			http_response_code(500);
		
		foreach ($vars as $var) {
			echo '<pre>';
			(gettype($var) === 'array' || gettype($var) === 'object') ? print_r($var) : print $var;
			echo '</pre>';
		}
		exit;
	}
}

if (!function_exists('errorBag')) {
	function errorBag(?string $key = NULL)
	{
		$error_bag = Validator::getErrorBag();
		if ($key)
			return $error_bag->get($key);
		return $error_bag;
	}
}

if (!function_exists('env')) {
	function env(string $name, ?string $default = '')
	{
		$env = parse_ini_file(getRootPath() . '/.env');
		return key_exists($name, $env) ? $env[$name] : $default;
	}
}

if (!function_exists('findFileRecursive')) {
	/**
	 * @param $directory
	 * @param $filename
	 * @return object{absolute:string,relative:string,name:string}|null
	 */
	function findFileRecursive($directory, $filename)
	{
		if (!is_dir($directory))
			return NULL; // or throw your own exception
		
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
		
		foreach ($iterator as $file) {
			$getFilename = $file->getFilename();
			if ($getFilename === $filename || $getFilename === strtolower($filename) || $getFilename === strtoupper($filename) || $getFilename === mb_convert_case($filename, MB_CASE_TITLE) || $getFilename === toKebabCase($filename)) {
				$absolutePath = $file->getPathname();
				$relativePath = ltrim(str_replace(PROJECT_ROOT, '', $absolutePath), DIRECTORY_SEPARATOR);
				return (object) [
					'relative' => correctDirPath($relativePath),
					'absolute' => $absolutePath,
					'name' => $file->getFilename(),
				];
			}
		}
		return NULL; // not found
	}
}
if (!function_exists('formatSize')) {
	function formatSize($bytes, int $precision = 2):string
	{
		$units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
		
		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);
		$bytes /= pow(1024, $pow);
		return round($bytes, $precision) . ' ' . $units[$pow];
	}
}

if (!function_exists('getRootPath')) {
	function getRootPath():false|string|null
	{
		return correctDirPath(defined('PROJECT_ROOT') ? PROJECT_ROOT : (defined('ROOT') ? ROOT : Dir::root()));
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
	function getViewFile($file, $ext = '.php'):string
	{
		$path = env('APP_VIEWS_DIR', 'views') . '/' . $file;
		return correctDirPath(getRootPath() . DIRECTORY_SEPARATOR . useDirectorySeparator($path) . ".$ext");
	}
}

if (!function_exists('loadFile')) {
	function loadFile($path, ?array $data = NULL)
	{
		$file = correctDirPath(getRootPath() . DIRECTORY_SEPARATOR . useDirectorySeparator($path) . '.php');
		if (is_readable($file)) {
			if (!empty($data))
				extract($data);
			require_once $file;
			return $file;
		}
		return false;
	}
}

if (!function_exists('mergePDF')) {
	function mergePDF($uploadedFiles, $mergedFile, $watermark = NULL)
	{
		$pdfInstance = new Fpdi();
		
		try {
			foreach ($uploadedFiles as $uploadedFile) {
				$pageCount = $pdfInstance->setSourceFile($uploadedFile);
				
				for ($i = 1; $i <= $pageCount; $i++) {
					try {
						$templateId = $pdfInstance->importPage($i);
						
						// get the size of the imported page
						$size = $pdfInstance->getTemplateSize($templateId);
						
						// create a page (landscape or portrait depending on the imported page size)
						$pdfInstance->AddPage($size['orientation'], [$size['width'], $size['height']]);
						
						// use the imported page
						$pdfInstance->useTemplate($templateId);
						
						$pdfInstance->SetFont('Helvetica');
						$pdfInstance->SetXY(5, 5);
						
						if (!empty($watermark))
							$pdfInstance->Write(8, $watermark);
					} catch (Exception $e) {
						http_response_code(400);
						echo json_encode(['success' => false, 'message' => $e->getMessage()]);
						exit;
					}
				}
			}
			$pdfInstance->Output('F', $mergedFile);
			return true;
		} catch (Exception $e) {
			return false;
		}
		return false;
	}
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
		return !empty($name) && !empty(Router::routes($name)) ? Router::routes($name)->generatedURI : throw new Error("Route name cannot be empty", 1);
	}
}

if (!function_exists('response')) {
	function response(string|array|object $data = NULL, $status = 200)
	{
		http_response_code($status);
		return $data;
	}
}

if (!function_exists('toKebabCase')) {
	function toKebabCase($string)
	{
		// Step 1: Insert dash before sequences of caps followed by lowercase (e.g., HTMLData → HTML-Data)
		$string = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1-$2', $string);
		
		// Step 2: Insert dash between lowercase/digit followed by uppercase (e.g., navBar → nav-Bar)
		$string = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $string);
		
		// Step 3: Lowercase everything
		return strtolower($string);
	}
}

if (!function_exists('useDirectorySeparator')) {
	function useDirectorySeparator($path)
	{
		return str_replace('/', DIRECTORY_SEPARATOR, $path);
	}
}

if (!function_exists('view')) {
	function view(string $view, ?array $data = []):View
	{
		return Engine::renderStatic($view, $data);
	}
}

if (!function_exists('xobject')) {
	function xobject():XObject
	{
		return new XObject();
	}
}
