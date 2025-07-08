<?php

namespace Hodos\Base;


use Hodos\Stack\XObject;

class Request
{
	protected mixed $original;
	
	protected ?string $route_uri;
	
	protected ?string $current_uri = FULL_URI;
	
	protected ?string $request_uri = BASE_REQUEST_URI;
	
	protected function __construct(public string $method)
	{
	}
	
	protected function build(array $props):static
	{
		foreach ($props as $key => $prop)
			$this->$key = $prop;
		return $this;
	}
	
	public function key($key)
	{
		return $this->$key;
	}
	
	public function add(mixed $key, mixed $value = NULL):void
	{
		$addKey = function ($key, $value) {
			$this->$key = $value;
			$this->original->$key = $value;
		};
		
		if (is_array($key) || is_object($key))
			foreach ($key as $idx => $value)
				$addKey($idx, $value);
		else
			$addKey($key, $value);
	}
	
	public function remove(mixed $key):void
	{
		$removeKey = function ($key) {
			unset($this->$key);
			unset($this->original->$key);
		};
		
		if (is_array($key) || is_object($key))
			foreach ($key as $value)
				$removeKey($value);
		else
			$removeKey($key);
	}
	
	public static function route()
	{
		return URL::route();
	}
	
	public static function toArray():array
	{
		return (array) URL::request()->original;
	}
	
	public function validate(array $rules):?XObject
	{
		return Validator::init($this->toArray(), $rules)->validate();
	}
}
