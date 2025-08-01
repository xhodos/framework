<?php

namespace Hodos\Stack;

use AllowDynamicProperties;
use Closure;
use Countable;

#[AllowDynamicProperties] class XObject
{
	private static ?XObject $instance = NULL;
	
	public function __construct()
	{
		if (!self::$instance || (self::$instance && (strtolower(get_class(self::$instance)) !== get_class($this))))
			self::$instance = $this;
	}
	
	public static function count():int
	{
		return count(self::toArray());
	}
	
	/*public static function exists($key) {
		$instance = self::$instance;
		return $instance;
	}*/
	
	public static function add($key, $value)
	{
		$instance = self::$instance;
		if (!$instance->has($key))
			$instance->$key = $value;
		// return $instance;
	}
	
	public static function fromArray($datum)
	{
		$instance = self::$instance;
		foreach ($datum as $key => $data)
			if (!$instance->has($key))
				$instance->$key = $data;
		return $instance;
	}
	
	public static function toArray():array
	{
		return (array) self::$instance;
	}
	
	public static function forEach(Closure $callback):void
	{
		foreach (self::toArray() as $key => $value)
			$callback($key, $value);
	}
	
	public static function get(string $key)
	{
		$instance = self::$instance;
		return $instance->has($key) ? $instance->$key : NULL;
	}
	
	public static function has(string $key):bool
	{
		$instance = self::$instance;
		if ($instance->$key)
			return true;
		return false;
	}
	
	public static function delete(string $key):void
	{
		$instance = self::$instance;
		if ($instance->$key)
			unset($instance->$key);
	}
}
