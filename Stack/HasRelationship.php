<?php

namespace Hodos\Stack;

use Error;
use Exception;

trait HasRelationship
{
	protected function belongsTo(string $model, $localKey = NULL, $foreignKey = 'id')
	{
		$belongs = $this->belongs($model, $localKey, $foreignKey);
		return $belongs ? $this->getRelationship($belongs) : NULL;
	}
	
	protected function hasOne(string $model, $foreignKey = NULL, $localKey = 'id')
	{
		$has = $this->has($model, $foreignKey, $localKey);
		return $has ? $this->getRelationship($has) : NULL;
	}
	
	protected function belongsToMany(string $model, $localKey = NULL, $foreignKey = 'id')
	{
		$belongs = $this->belongs($model, $localKey, $foreignKey);
		return $belongs ? $this->getRelationship($belongs, true) : NULL;
	}
	
	protected function hasMany(string $model, $foreignKey = NULL, $localKey = 'id')
	{
		$has = $this->has($model, $foreignKey, $localKey);
		return $has ? $this->getRelationship($has, true) : NULL;
	}
	
	private function belongs(string $model, $localKey, $foreignKey)
	{
		if (class_exists($model)) {
			$relatedClass = new $model;
			$this->checkKeys($this, $relatedClass, $localKey);
			
			if (empty($this->$localKey))
				dd(new Error("Unknown column: $localKey; in table $this->table", 1));
			return $relatedClass::where([$foreignKey => $this->$localKey]);
		}
		return false;
	}
	
	private function has(string $model, $foreignKey, $localKey)
	{
		if (class_exists($model)) {
			$relatedClass = new $model;
			$this->checkKeys($relatedClass, $this, $foreignKey);
			
			if (empty($this->$localKey))
				dd(new Error("Unknown column: $localKey; in table $this->table", 1));
			return $relatedClass::where([$foreignKey => $this->$localKey]);
		}
		return false;
	}
	
	private function getRelationship($relationship, $many = false)
	{
		if (!$many) {
			$related = $relationship->first();
			$related->related = $this;
		} else {
			$related = $relationship->get();
			foreach ($related as $value)
				$value->related = $this;
		}
		return $related;
	}
	
	private function checkKeys($relatedClass, $class, $key):void
	{
		$foreignKeyExists = false;
		if (!$key)
			$key = getUnderscoredClassName(get_class($class)) . '_id';
		
		foreach ($relatedClass->showTableColumnData() as $columns)
			if (strtolower($columns->Field) === strtolower($key)) {
				$foreignKeyExists = true;
				break;
			}
		if (!$foreignKeyExists)
			dd(new Error("Unknown column: $key; in table $relatedClass->table", 1));
	}
}
