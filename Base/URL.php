<?php

namespace Hodos\Base;

class URL
{
	public static ?string $base = URI;
	
	private mixed $routeStack;
	
	private static ?URL $instance = NULL;
	
	public function __construct()
	{
		self::$instance = $this;
		$this->routeStack = Route::currentStack()['stack-info'];
	}
	
	public static function request()
	{
		self::returnInstance();
		return self::$instance->routeStack->request;
	}
	
	public static function route()
	{
		self::returnInstance();
		return self::$instance->routeStack->route;
	}
	
	private static function returnInstance()
	{
		if (!self::$instance)
			new self();
	}
}
