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
	
	public function add($key, $value):void
	{
		$this->$key = $value;
	}
	
	public function remove($key):void
	{
		unset($this->$key);
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
