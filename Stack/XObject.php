<?php

namespace Hodos\Stack;

use Closure;
use Countable;

class XObject
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
	
	public static function fromArray($datum)
	{
		$instance = self::$instance;
		foreach ($datum as $key => $data)
			if (!property_exists($instance, $key))
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
		if ($instance->$key)
			return $instance->$key;
	}
	
	public static function delete(string $key):void
	{
		$instance = self::$instance;
		if ($instance->$key)
			unset($instance->$key);
	}
}
