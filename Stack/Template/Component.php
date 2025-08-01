<?php
namespace Hodos\Stack\Template;

use http\Exception\RuntimeException;
use ReflectionClass;
use ReflectionProperty;

abstract class Component
{
	public string $slot = '';
	
	protected static ?self $instance = NULL;
	protected array $data = [];
	
	abstract public function render():View|string;
	
	public static function make(array $params = []):static
	{
		$instance = new static();
		// $instance->data = $params ? $params[0] : [];
		$instance->setProps($params);
		return $instance;
	}
	
	public function output():View|string
	{
		$componentReflection = new ReflectionClass($this);
		$user_defined_properties = $componentReflection->getProperties(ReflectionProperty::IS_PUBLIC);
		
		foreach ($user_defined_properties as $property)
			$this->data[$property->getName()] = $property->getValue($this);
		$rendered = $this->render();
		
		if (!($rendered instanceof View))
			// Inject the data automatically
			throw new RuntimeException('');
		// $rendered->params = array_merge($this->data, $rendered->params ?? []);
		$rendered->params = array_merge($this->data, $rendered->params);
		return $rendered->render();
	}
	
	protected function setProps(array $props):void
	{
		foreach ($props as $key => $value) {
			if (property_exists($this, $key))
				$this->{$key} = $value;
		}
	}
}
