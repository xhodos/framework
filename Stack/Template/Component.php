<?php
namespace Hodos\Stack\Template;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

abstract class Component
{
	public string $slot = '';
	protected array $data = [];
	
	protected static int $maxDepth = 1;
	protected static int $renderDepth = 0;
	
	abstract public function render():View|string;
	
	public static function make(array $params = []):static
	{
		$instance = new static();
		$instance->setProps($params);
		return $instance;
	}
	
	public function output()
	{
		$cacheKey = $this->generateCacheKey();
		if ($cached = cache()->get($cacheKey))
			return $cached;
		$view = $this->render();
		
		if ($view instanceof View)
			$view->params = array_merge($this->collectPublicProperties(), $view->params ?? []);
		
		if (!is_string($view) && !($view instanceof View))
			dd(new RuntimeException("Component::render() must return string or instance of View."));
		cache()->set($cacheKey, $view);
		return $view;
	}
	
	public function withSlot(string $content):static
	{
		$this->slot = $content;
		return $this;
	}
	
	protected function generateCacheKey():string
	{
		$class = static::class;
		$data = $this->collectPublicProperties();
		$hash = md5($class . serialize($data));
		return "component_cache_$hash";
	}
	
	protected function setProps(array $props):void
	{
		foreach ($props as $key => $value)
			if (property_exists($this, $key))
				$this->$key = $value;
			else
				$this->data[$key] = $value;
	}
	
	protected function collectPublicProperties():array
	{
		$reflection = new ReflectionClass($this);
		$props = [];
		
		foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $prop)
			$props[$prop->getName()] = $prop->getValue($this);
		return array_merge($props, $this->data);
	}
}
