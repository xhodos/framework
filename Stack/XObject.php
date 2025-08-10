<?php

namespace Hodos\Stack;

use AllowDynamicProperties;
use Closure;
use Countable;

#[AllowDynamicProperties] class XObject
{
	private static ?XObject $instance = NULL;
	
	private static function __instantiate()
	{
		if (!self::$instance)
			self::$instance = new static();
		return self::$instance;
	}
	
	public static function count():int
	{
		return count(self::toArray());
	}
	
	/*public static function exists($key) {
		$instance = self::__instantiate();
		return $instance;
	}*/
	public static function stack()
	{
		return self::__instantiate();
	}
	
	public static function add($key, $value)
	{
		$instance = self::__instantiate();
		if (!$instance->has($key))
			$instance->$key = $value;
	}
	
	public static function fromArray($datum)
	{
		$instance = self::__instantiate();
		foreach ($datum as $key => $data) {
			if (!$instance->has($key))
				$instance->{$key} = $data;
		}
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
		$instance = self::__instantiate();
		return $instance->has($key) ? $instance->$key : NULL;
	}
	
	public static function has(string $key):bool
	{
		$instance = self::__instantiate();
		if (property_exists($instance, $key))
			return true;
		return false;
	}
	
	public static function delete(string $key):void
	{
		$instance = self::__instantiate();
		if ($instance->$key)
			unset($instance->$key);
	}
}
