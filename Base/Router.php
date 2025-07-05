<?php

namespace Hodos\Base;

use Exception;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Hodos\Stack\XObject;

class Router extends Request
{
	protected static ?int $matchCount = 0;
	
	public static bool $routeMatched = false;
	
	protected static $instance;
	
	protected static ?array $routes = [];
	
	protected static ?array $namedRoutes = [];
	
	protected static ?array $stack = [];
	
	public static ?array $acceptedRouteMethods = [];
	
	public function __construct(public mixed $routeInfo)
	{
		// parent::__construct($routeInfo);
		
		$exists = false;
		$build_request = $this->init($routeInfo);
		
		if (!$this::$instance)
			$this::$instance = $this;
		
		if (!empty(self::$routes['routes']))
			foreach (self::$routes['routes'] as $key => $value)
				if ($value->uri === $routeInfo->uri && $value->method === $routeInfo->method) {
					$exists = true;
					break;
				}
		
		if (!$exists) {
			$routeInfo->generatedURI = BASE_URI . '/' . $routeInfo->uri;
			self::$routes['routes'][] = $routeInfo;
			
			self::$routes['original_uri'] = $this->current_uri;
			self::$routes['requested_uri'] = $this->request_uri;
			
			
			if ($build_request) {
				self::$stack['stack-info'] = $build_request;
				self::$stack['route-info'] = $routeInfo;
			}
		}
	}
	
	public function name(string $name)
	{
		if (!empty(self::$routes['routes']))
			foreach (self::$routes['routes'] as $route) {
				if ($route->uri === $this->route_uri) {
					$route->is_named = true;
					$route->name = $name;
					self::$namedRoutes[$name] = $route;
				}
			}
	}
	
	public static function routes(?string $name = NULL)
	{
		return $name ? (key_exists($name, Router::$namedRoutes) ? Router::$namedRoutes[$name] : die("Route <strong><em>'$name'</em></strong> is undefined.")) : Router::$routes;
	}
	
	/**
	 * @throws Exception
	 */
	public function getFuncArgs(object $build_request, ReflectionMethod|ReflectionFunction $method):array
	{
		$args = [];
		$method_parameter_count = $method->getNumberOfRequiredParameters();
		
		for ($i = 0; $i < $method_parameter_count; $i++) {
			$argument = $method->getParameters()[$i];
			$argument_name = $argument->getName();
			$argument_type = $argument->getType()->getName();
			
			$request_class_name = new ReflectionClass(Request::class)->getName();
			/* $model_class_name = (new ReflectionClass(Model::class))->getShortName();
			$arg_parent_class_name = (new ReflectionClass($argument_type))->getParentClass(); */
			// $args[$argument_name] = $argument_type === $request_class_name ? $build_request->request : (!$argument->getType()->isBuiltin() ? (($arg_parent_class_name && $arg_parent_class_name->getShortName() === $model_class_name) ? $argument_type::instantiate() : new $argument_type) : NULL);
			$args[$argument_name] = $argument_type === $request_class_name ? $build_request->request : (!$argument->getType()->isBuiltin() ? new $argument_type : NULL);
			
			// TODO: Select Model by id
			if ($argument_type !== $request_class_name && !$argument->getType()->isBuiltin()) {
				if (!empty($build_request->request->$argument_name)) {
					$model_id = $build_request->request->$argument_name;
					$results = $args[$argument_name]::where(['id' => $model_id])->get();
					
					if (!empty($results))
						foreach ($results[0] as $key => $value)
							$args[$argument_name]->$key = $value;
					else
						throw new Exception("Unable to find id: $model_id in Table: " . $args[$argument_name]->getTable(), 1);
				}
			}
		}
		return $args;
	}
	
	/**
	 * @throws Exception
	 */
	private function init($routeInfo):object|false|array
	{
		if (!empty($routeInfo->action)) {
			$total_matched = 0;
			$param_exp = '/\{(\w+)\}/';
			$this->route_uri = $routeInfo->uri;
			
			$_request_uri = trim($this->request_uri, '/');
			$_current_uri = trim($this->route_uri, '/');
			
			$_current_uri_exploded = explode('/', $_current_uri);
			$_request_uri_exploded = explode('/', $_request_uri);
			
			if (count($_current_uri_exploded) === count($_request_uri_exploded)) {
				for ($i = 0; $i < count($_current_uri_exploded); $i++)
					$total_matched += (preg_split('/\?|\#/', $_request_uri_exploded[$i])[0] === $_current_uri_exploded[$i] || preg_match_all($param_exp, $_current_uri_exploded[$i], $matches)) ? 1 : 0;
				
				if ($total_matched === count($_current_uri_exploded))
					if (strtolower($_SERVER['REQUEST_METHOD']) === 'head' || strtolower($routeInfo->method) === 'any' || strtolower($routeInfo->method) === strtolower($_SERVER['REQUEST_METHOD'])) {
						$build_parameters = [];
						$parameters = new XObject();
						$request_parameters = strtolower($routeInfo->method) === 'get' ? $_REQUEST : array_merge($_REQUEST, $_FILES);
						
						if (preg_match_all($param_exp, $this->route_uri, $matches)) {
							$keys = $matches[1];
							$exploded_uri = explode('/', $_request_uri);
							$values = array_splice($exploded_uri, 1);
							
							if (count($keys) === count($values)) {
								foreach ($keys as $key => $value)
									$parameters->$value = preg_split('/\?|\#/', $values[$key])[0];
								$routeInfo->parameters = $parameters;
							}
						}
						
						foreach ($request_parameters as $key => $value)
							$parameters->$key = $value;
						
						foreach ($parameters as $key => $value)
							$build_parameters[$key] = $value;
						
						
						$REQUEST = new parent($routeInfo->method);
						$REQUEST->original = $parameters;
						$REQUEST->route_uri = $routeInfo->uri;
						
						return (object) ['route' => $routeInfo, 'request' => $REQUEST->build($build_parameters)];
					} else {
						self::$acceptedRouteMethods[$routeInfo->uri][] = $routeInfo->method;
						return [];
					}
			}
		} else
			throw new Exception("The route action requires either an array or a closure.", 1);
		return false;
	}
}
