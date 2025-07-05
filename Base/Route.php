<?php

namespace Hodos\Base;

use Closure;

class Route extends Router
{
	public static function all()
	{
		return parent::routes();
	}
	
	public static function currentStack()
	{
		return parent::$stack;
	}
	
	public static function router():Router|Route
	{
		return parent::$instance;
	}
	
	public static function any(string $uri, array|Closure $action):Router
	{
		return Route::setRoute($uri, $action, 'any');
	}
	
	public static function get(string $uri, array|Closure $action):Router
	{
		return Route::setRoute($uri, $action, 'get');
	}
	
	public static function post(string $uri, array|Closure $action):Router
	{
		return Route::setRoute($uri, $action, 'post');
	}
	
	public static function put(string $uri, array|Closure $action):Router
	{
		return Route::setRoute($uri, $action, 'put');
	}
	
	public static function patch(string $uri, array|Closure $action):Router
	{
		return Route::setRoute($uri, $action, 'patch');
	}
	
	public static function delete(string $uri, array|Closure $action)
	{
		return Route::setRoute($uri, $action, 'patch');
	}
	
	/**
	 * Summary of setRoute
	 *
	 * @param string $uri
	 * @param array|\Closure $action
	 * @param string $method 'any' | 'get' | 'post' | 'put' | 'patch' | 'delete'
	 * @return Router
	 */
	private static function setRoute(string $uri, array|Closure $action, string $method = 'any' | 'get' | 'post' | 'put' | 'patch' | 'delete'):Router
	{
		$current = false;
		$is_named = false;
		$uri = trim($uri, '/');
		return new Router((object) compact('action', 'uri', 'method', 'current', 'is_named'));
	}
}
